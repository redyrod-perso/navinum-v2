<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SessionController extends AbstractController
{
    private const SESSIONS_DIR = '/var/cache/sessions';

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
        $file = $this->getSessionsPath() . '/' . $sessionId . '.json';
        file_put_contents($file, json_encode($session));
    }

    #[Route('/api/session/create', name: 'api_session_create', methods: ['POST'])]
    public function createSession(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $playerName = $data['playerName'] ?? null;
        $customSessionId = $data['sessionId'] ?? null;

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
                        'joinedAt' => time()
                    ];
                    $this->saveSession($sessionId, $existingSession);
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
            'status' => 'lobby', // lobby, playing, finished
            'theme' => null,
            'players' => [
                [
                    'name' => $playerName,
                    'score' => 0,
                    'joinedAt' => time()
                ]
            ],
            'createdAt' => time()
        ];

        $this->saveSession($sessionId, $session);

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
                'joinedAt' => time()
            ];
            $this->saveSession($sessionId, $session);
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

        if (!$theme) {
            return new JsonResponse(['error' => 'Thème manquant'], 400);
        }

        $session = $this->getSession($sessionId);
        if (!$session) {
            return new JsonResponse(['error' => 'Session non trouvée'], 404);
        }

        if ($session['status'] !== 'lobby') {
            return new JsonResponse(['error' => 'Le quiz a déjà commencé'], 400);
        }

        // Démarrer le quiz
        $session['status'] = 'playing';
        $session['theme'] = $theme;
        $session['startedAt'] = time();
        $this->saveSession($sessionId, $session);

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
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

        return new JsonResponse(['status' => 'ok', 'session' => $session]);
    }
}
