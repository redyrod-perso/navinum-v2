<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

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

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
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

    private function getSession(string $sessionId): ?array
    {
        $file = $this->getSessionsPath() . '/' . $sessionId . '.json';
        if (!file_exists($file)) {
            return null;
        }
        return json_decode(file_get_contents($file), true);
    }

    private function saveSession(string $sessionId, array $session): void
    {
        $session['lastUpdate'] = time();
        $file = $this->getSessionsPath() . '/' . $sessionId . '.json';
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
            return new JsonResponse(['error' => 'Nom du joueur manquant'], 400);
        }

        // Utiliser un ID personnalisé ou générer un ID de session unique
        $sessionId = $customSessionId ?: substr(md5(uniqid('quiz_', true)), 0, 8);

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
        }

        // Créer une nouvelle session
        $session = [
            'id' => $sessionId,
            'rfidGroupeName' => $rfidGroupeName,
            'leader' => $playerName, // Le créateur est le leader
            'status' => 'lobby',
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
            return new JsonResponse(['error' => 'Nom du joueur manquant'], 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        // Vérifier si le quiz a déjà commencé
        if ($session['status'] !== 'lobby') {
            return new JsonResponse(['error' => 'Le quiz a déjà commencé'], 400);
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
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        return new JsonResponse(['session' => $session]);
    }

    #[Route('/api/session/{sessionId}/leave', name: 'api_session_leave', methods: ['POST'])]
    public function leaveSession(string $sessionId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;

        if (!$playerName) {
            return new JsonResponse(['error' => 'Nom du joueur manquant'], 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        // Retirer le joueur de la session
        $session['players'] = array_values(array_filter($session['players'], function($player) use ($playerName) {
            return $player['name'] !== $playerName;
        }));

        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
    }

    #[Route('/api/session/{sessionId}/reset', name: 'api_session_reset', methods: ['POST'])]
    public function resetSession(string $sessionId): JsonResponse
    {
        $file = $this->getSessionsPath() . '/' . $sessionId . '.json';

        if (file_exists($file)) {
            unlink($file);
        }

        return new JsonResponse(['status' => 'ok', 'message' => 'Session réinitialisée']);
    }

    #[Route('/api/sessions/clear', name: 'api_sessions_clear', methods: ['POST'])]
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
            return new JsonResponse(['error' => 'Thème manquant'], 400);
        }

        if (!$playerName) {
            return new JsonResponse(['error' => 'Nom du joueur manquant'], 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        if ($session['status'] !== 'lobby') {
            return new JsonResponse(['error' => 'Le quiz a déjà commencé'], 400);
        }

        // Vérifier que le joueur est bien le leader
        if (!isset($session['leader']) || $session['leader'] !== $playerName) {
            return new JsonResponse([
                'error' => 'Seul le leader du groupe peut démarrer le quiz'
            ], 403);
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
        $score = $data['score'] ?? 0;

        if (!$playerName) {
            return new JsonResponse(['error' => 'Nom du joueur manquant'], 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        // Mettre à jour le score du joueur
        foreach ($session['players'] as &$player) {
            if ($player['name'] === $playerName) {
                $player['score'] = $score;
                break;
            }
        }
        $this->saveSession($sessionId, $session);
        $this->notifySessionUpdate($sessionId);

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
    }
}
