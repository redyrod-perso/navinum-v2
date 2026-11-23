<?php

namespace App\Controller;

use App\Repository\LaunchTokenRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sessions de quiz multijoueur.
 *
 * Temps réel : chaque changement de session est publié sur le hub Mercure intégré à FrankenPHP,
 * topic `/navinum/sessions/{id}`, message `{"type": "session", "session": {…}}`.
 * Les clients s'y abonnent (EventSource) : aucune connexion PHP ne reste ouverte.
 */
class SessionController extends AbstractController
{
    private const SESSIONS_DIR = '/var/cache/sessions';

    /** Une partie sans activité depuis ce délai est considérée comme abandonnée (le groupe est libéré). */
    public const ABANDON_DELAY = 15 * 60;

    private const PARTIE_EN_COURS = 'Une partie est en cours dans ce groupe : attendez la fin ou choisissez un autre groupe.';

    /** Longueur maximale d'un identifiant de session (nom du groupe). */
    public const ID_MAX = 100;

    /** Joueurs maximum par groupe sans configuration de lancement (= défaut `joueursMax` du manifeste du quiz). */
    public const MAX_PLAYERS_DEFAULT = 8;
    /** Plafond absolu, quelle que soit la configuration. */
    public const MAX_PLAYERS_LIMIT = 50;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
        private readonly LaunchTokenRepository $launchTokens,
    ) {
    }

    /** Topic Mercure d'une session de quiz. */
    public static function sessionTopic(string $sessionId): string
    {
        return '/navinum/sessions/'.$sessionId;
    }

    private function getSessionsPath(): string
    {
        $path = $this->getParameter('kernel.project_dir') . self::SESSIONS_DIR;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    /**
     * Fichier d'une session : nom dérivé d'un hachage de l'identifiant (le nom du groupe, saisi par
     * les joueurs), pour que n'importe quel nom reste un simple fichier du dossier des sessions.
     */
    private function sessionFile(string $sessionId): string
    {
        return $this->getSessionsPath().'/'.hash('sha256', $sessionId).'.json';
    }

    private static function isValidId(string $sessionId): bool
    {
        return '' !== trim($sessionId) && mb_strlen($sessionId) <= self::ID_MAX && !preg_match('/[\x00-\x1f]/', $sessionId);
    }

    /** Réponse d'erreur avec un code métier stable, lu par la librairie (`NavinumAPI.erreurs`). */
    private static function error(string $message, string $code, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message, 'code' => $code], $status);
    }

    /**
     * Nombre maximum de joueurs : `joueursMax` de la configuration du lancement (jeton `?nv=` transmis par
     * l'interactif, configuration fixée côté serveur), sinon MAX_PLAYERS_DEFAULT ; borné à 1..MAX_PLAYERS_LIMIT.
     */
    private function maxPlayers(mixed $launchToken): int
    {
        $max = self::MAX_PLAYERS_DEFAULT;
        if (\is_string($launchToken) && '' !== $launchToken && \strlen($launchToken) <= 128) {
            $token = $this->launchTokens->findOneByToken($launchToken);
            if (null !== $token && !$token->isExpired() && is_numeric($token->getConfig()['joueursMax'] ?? null)) {
                $max = (int) $token->getConfig()['joueursMax'];
            }
        }

        return max(1, min(self::MAX_PLAYERS_LIMIT, $max));
    }

    private static function groupeComplet(array $session): JsonResponse
    {
        $max = $session['maxPlayers'] ?? self::MAX_PLAYERS_DEFAULT;

        return self::error(\sprintf('Ce groupe est complet (%d joueurs maximum) : choisissez-en un autre.', $max), 'GROUPE_COMPLET', 409);
    }

    private static function isFull(array $session): bool
    {
        return \count($session['players'] ?? []) >= ($session['maxPlayers'] ?? self::MAX_PLAYERS_DEFAULT);
    }

    private function getSession(string $sessionId): ?array
    {
        $file = $this->sessionFile($sessionId);
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    /**
     * Partie terminée ou abandonnée : plus en lobby, et soit plus aucun joueur, soit plus d'activité depuis
     * ABANDON_DELAY. Le groupe peut alors repartir sur un nouveau lobby.
     */
    private function isOver(array $session): bool
    {
        if ('lobby' === ($session['status'] ?? null)) {
            return false;
        }

        return [] === ($session['players'] ?? []) || (time() - ($session['lastUpdate'] ?? 0)) > self::ABANDON_DELAY;
    }

    private function deleteSession(string $sessionId): void
    {
        $file = $this->sessionFile($sessionId);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function saveSession(string $sessionId, array $session): void
    {
        $session['lastUpdate'] = time();
        $file = $this->sessionFile($sessionId);
        file_put_contents($file, json_encode($session));
    }

    /** Publie l'état courant de la session aux joueurs abonnés (best effort : n'interrompt jamais le jeu). */
    private function notifySessionUpdate(string $sessionId): void
    {
        $session = $this->getSession($sessionId);
        if (null === $session) {
            return;
        }
        try {
            $this->hub->publish(new Update(
                self::sessionTopic($sessionId),
                json_encode(['type' => 'session', 'session' => $session], \JSON_UNESCAPED_UNICODE),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Publication Mercure de la session impossible', ['session' => $sessionId, 'error' => $e->getMessage()]);
        }
    }

    #[Route('/api/session/create', name: 'api_session_create', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;
        $customSessionId = $data['sessionId'] ?? null;
        $rfidGroupeName = $data['rfidGroupeName'] ?? null;

        if (!$playerName) {
            return self::error('Nom du joueur manquant', 'VALIDATION', 400);
        }

        // Utiliser un ID personnalisé ou générer un ID de session unique
        $sessionId = $customSessionId ?: substr(md5(uniqid('quiz_', true)), 0, 8);
        if (!\is_string($sessionId) || !self::isValidId($sessionId)) {
            return self::error(\sprintf('Nom de groupe invalide (1 à %d caractères).', self::ID_MAX), 'SESSION_INVALIDE', 400);
        }

        // Vérifier si la session existe déjà (pour les sessions personnalisées)
        $existingSession = $this->getSession($sessionId);
        if ($existingSession) {
            // Si la session existe et est en lobby, ajouter le joueur
            if ($existingSession['status'] === 'lobby') {
                // Vérifier si le joueur existe déjà
                $playerExists = false;
                foreach ($existingSession['players'] as $player) {
                    if ($player['name'] === $playerName) {
                        $playerExists = true;
                        break;
                    }
                }

                if (!$playerExists) {
                    if (self::isFull($existingSession)) {
                        return self::groupeComplet($existingSession);
                    }
                    $existingSession['players'][] = [
                        'name' => $playerName,
                        'score' => 0,
                        'joinedAt' => time(),
                        'isLeader' => false
                    ];
                    $this->saveSession($sessionId, $existingSession);
                    $this->notifySessionUpdate($sessionId);
                }

                return new JsonResponse([
                    'sessionId' => $sessionId,
                    'session' => $existingSession
                ]);
            }

            // Partie réellement en cours : ne pas l'écraser
            if (!$this->isOver($existingSession)) {
                return self::error(self::PARTIE_EN_COURS, 'PARTIE_EN_COURS', 409);
            }
            // Partie terminée ou abandonnée : on repart sur un nouveau lobby (ci-dessous)
        }

        // Créer une nouvelle session
        $session = [
            'id' => $sessionId,
            'rfidGroupeName' => $rfidGroupeName,
            'leader' => $playerName, // Le créateur est le leader
            'status' => 'lobby',
            'maxPlayers' => $this->maxPlayers($data['launchToken'] ?? null),
            'theme' => null,
            'players' => [
                [
                    'name' => $playerName,
                    'score' => 0,
                    'joinedAt' => time(),
                    'isLeader' => true
                ]
            ],
            'createdAt' => time(),
            'lastUpdate' => time()
        ];

        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        return new JsonResponse([
            'sessionId' => $sessionId,
            'session' => $session
        ]);
    }

    #[Route('/api/session/{sessionId}/join', name: 'api_session_join', methods: ['POST'])]
    public function joinSession(string $sessionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;

        if (!$playerName) {
            return self::error('Nom du joueur manquant', 'VALIDATION', 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return self::error('Session non trouvée', 'SESSION_INTROUVABLE', 404);
        }

        if ($session['status'] !== 'lobby') {
            if (!$this->isOver($session)) {
                return self::error(self::PARTIE_EN_COURS, 'PARTIE_EN_COURS', 409);
            }
            // Partie terminée ou abandonnée : nouveau lobby, le joueur qui arrive en devient le leader
            $session = [
                'id' => $sessionId,
                'rfidGroupeName' => $session['rfidGroupeName'] ?? null,
                'leader' => $playerName,
                'status' => 'lobby',
                'maxPlayers' => $this->maxPlayers($data['launchToken'] ?? null),
                'theme' => null,
                'players' => [['name' => $playerName, 'score' => 0, 'joinedAt' => time(), 'isLeader' => true]],
                'createdAt' => time(),
            ];
            $this->saveSession($sessionId, $session);
            $this->notifySessionUpdate($sessionId);

            return new JsonResponse(['session' => $this->getSession($sessionId)]);
        }

        // Vérifier si le joueur existe déjà
        $playerExists = false;
        foreach ($session['players'] as $player) {
            if ($player['name'] === $playerName) {
                $playerExists = true;
                break;
            }
        }

        if (!$playerExists) {
            if (self::isFull($session)) {
                return self::groupeComplet($session);
            }
            $session['players'][] = [
                'name' => $playerName,
                'score' => 0,
                'joinedAt' => time(),
                'isLeader' => false
            ];
            $this->saveSession($sessionId, $session);
            $this->notifySessionUpdate($sessionId);
        }

        return new JsonResponse(['session' => $session]);
    }

    #[Route('/api/session/{sessionId}', name: 'api_session_get', methods: ['GET'])]
    public function getSessionInfo(string $sessionId): JsonResponse
    {
        $session = $this->getSession($sessionId);
        if (!$session) {
            return self::error('Session non trouvée', 'SESSION_INTROUVABLE', 404);
        }

        return new JsonResponse(['session' => $session]);
    }

    #[Route('/api/session/{sessionId}/leave', name: 'api_session_leave', methods: ['POST'])]
    public function leaveSession(string $sessionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;

        if (!$playerName) {
            return self::error('Nom du joueur manquant', 'VALIDATION', 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return self::error('Session non trouvée', 'SESSION_INTROUVABLE', 404);
        }

        // Retirer le joueur de la session
        $session['players'] = array_values(array_filter($session['players'], function($player) use ($playerName) {
            return $player['name'] !== $playerName;
        }));

        // Plus personne : la session est supprimée, le groupe est libre pour une nouvelle partie
        if ([] === $session['players']) {
            $this->deleteSession($sessionId);

            return new JsonResponse(['status' => 'ok', 'session' => null]);
        }

        // Le leader est parti : le joueur arrivé le plus tôt prend le relais
        if (($session['leader'] ?? null) === $playerName) {
            $session['leader'] = $session['players'][0]['name'];
            foreach ($session['players'] as $i => $player) {
                $session['players'][$i]['isLeader'] = 0 === $i;
            }
        }

        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
    }

    /** Maintenance : réservée à l'administration. */
    #[Route('/api/session/{sessionId}/reset', name: 'api_session_reset', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function resetSession(string $sessionId): JsonResponse
    {
        $this->deleteSession($sessionId);

        return new JsonResponse(['status' => 'ok', 'message' => 'Session réinitialisée']);
    }

    /** Maintenance : réservée à l'administration (en local : `make sessions-reset`). */
    #[Route('/api/sessions/clear', name: 'api_sessions_clear', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function clearAllSessions(): JsonResponse
    {
        $path = $this->getSessionsPath();
        $files = glob($path . '/*.json');

        $count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }

        return new JsonResponse([
            'status' => 'ok',
            'message' => "Toutes les sessions ont été supprimées",
            'count' => $count
        ]);
    }

    #[Route('/api/session/{sessionId}/start', name: 'api_session_start', methods: ['POST'])]
    public function startSession(string $sessionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $theme = $data['theme'] ?? null;
        $playerName = $data['playerName'] ?? null;

        if (!$theme) {
            return self::error('Thème manquant', 'VALIDATION', 400);
        }

        if (!$playerName) {
            return self::error('Nom du joueur manquant', 'VALIDATION', 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return self::error('Session non trouvée', 'SESSION_INTROUVABLE', 404);
        }

        if ($session['status'] !== 'lobby') {
            return self::error('Le quiz a déjà commencé', 'PARTIE_DEMARREE', 409);
        }

        // Vérifier que le joueur est bien le leader
        if (!isset($session['leader']) || $session['leader'] !== $playerName) {
            return self::error('Seul le leader du groupe peut démarrer le quiz', 'NON_LEADER', 403);
        }

        // Démarrer le quiz
        $session['status'] = 'playing';
        $session['theme'] = $theme;
        $session['startedAt'] = time();
        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        // Réponse minimale (le frontend ne l'utilise plus, il se redirige immédiatement)
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/api/session/{sessionId}/score', name: 'api_session_score', methods: ['POST'])]
    public function updateScore(string $sessionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;
        $score = $data['score'] ?? null;

        if (!$playerName) {
            return self::error('Nom du joueur manquant', 'VALIDATION', 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return self::error('Session non trouvée', 'SESSION_INTROUVABLE', 404);
        }

        // Score plausible uniquement : partie en cours, joueur du groupe, +1 au plus par appel
        // (une bonne réponse à la fois ; renvoyer le même score est sans effet)
        if (!\is_int($score) || 'playing' !== ($session['status'] ?? null)) {
            return self::error('Score refusé : aucune partie en cours ou score invalide.', 'SCORE_REFUSE', 409);
        }
        $index = null;
        foreach ($session['players'] as $i => $player) {
            if ($player['name'] === $playerName) {
                $index = $i;
                break;
            }
        }
        $current = null === $index ? null : (int) ($session['players'][$index]['score'] ?? 0);
        if (null === $current || $score < $current || $score > $current + 1) {
            return self::error('Score refusé : il ne peut augmenter que d\'un point par bonne réponse.', 'SCORE_REFUSE', 409);
        }
        $session['players'][$index]['score'] = $score;
        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
    }
}
