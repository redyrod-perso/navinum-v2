<?php

namespace App\Controller\Api;

use App\Entity\LaunchToken;
use App\Gamification\GamificationService;
use App\Repository\LaunchTokenRepository;
use App\Service\LaunchService;
use App\Service\VisiteurToken;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API de lancement des interactifs.
 *
 * - GET  /api/launch/{token}         contexte : interactif, parcours, exposition, config fusionnée, visiteur
 * - POST /api/launch/{token}/visiteur rattache le visiteur (Authorization: Bearer <jeton visiteur>)
 * - POST /api/launch/{token}/events  événement {type, payload} → consigné dans LogVisite,
 *                                    puis gamification (XP, médailles) renvoyée dans `gamification`
 *
 * Codes : 404 jeton inconnu, 410 jeton expiré, 400 requête invalide.
 */
#[Route('/api/launch/{token}', name: 'api_launch_', requirements: ['token' => '[a-f0-9]{48}'])]
final class LaunchController extends AbstractController
{
    public function __construct(
        private readonly LaunchTokenRepository $tokens,
        private readonly LaunchService $launch,
        private readonly VisiteurToken $visiteurTokens,
        private readonly GamificationService $gamification,
    ) {
    }

    #[Route('', name: 'context', methods: ['GET'])]
    public function context(string $token): JsonResponse
    {
        $launchToken = $this->tokens->findOneByToken($token);
        if ($error = $this->tokenError($launchToken)) {
            return $error;
        }

        return $this->json($this->launch->context($launchToken));
    }

    /**
     * Rattache le visiteur identifié (Authorization: Bearer <jeton visiteur>) à ce lancement :
     * ses événements alimenteront ensuite son LogVisite (et sa gamification).
     * 401 jeton visiteur invalide, 409 lancement déjà rattaché à un autre visiteur.
     */
    #[Route('/visiteur', name: 'identify', methods: ['POST'])]
    public function identify(string $token, Request $request): JsonResponse
    {
        $launchToken = $this->tokens->findOneByToken($token);
        if ($error = $this->tokenError($launchToken)) {
            return $error;
        }
        $visiteur = $this->visiteurTokens->fromRequest($request);
        if (null === $visiteur) {
            return $this->json(['error' => 'Jeton visiteur absent ou invalide', 'code' => 'NON_IDENTIFIE'], 401);
        }
        if (!$this->launch->identify($launchToken, $visiteur)) {
            return $this->json(['error' => 'Ce lancement est déjà rattaché à un autre visiteur', 'code' => 'DEJA_RATTACHE'], 409);
        }

        return $this->json($this->launch->context($launchToken));
    }

    #[Route('/events', name: 'events', methods: ['POST'])]
    public function events(string $token, Request $request): JsonResponse
    {
        $launchToken = $this->tokens->findOneByToken($token);
        if ($error = $this->tokenError($launchToken)) {
            return $error;
        }

        $body = json_decode($request->getContent(), true);
        $type = \is_array($body) ? ($body['type'] ?? null) : null;
        if (!\is_string($type) || !preg_match('/^[a-z0-9_.-]{1,64}$/i', $type)) {
            return $this->json(['error' => 'Champ "type" obligatoire (lettres, chiffres, . _ -, 64 max)', 'code' => 'VALIDATION'], 400);
        }
        $payload = $body['payload'] ?? [];
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Le champ "payload" doit être un objet', 'code' => 'VALIDATION'], 400);
        }
        if (\strlen(json_encode($payload)) > 65536) {
            return $this->json(['error' => 'Payload trop volumineux (64 Ko max)', 'code' => 'VALIDATION'], 400);
        }

        $log = $this->launch->recordEvent($launchToken, $type, $payload);

        // Gamification : XP et médailles selon les règles de l'interactif (null sans visiteur identifié)
        $gamification = $this->gamification->apply(
            $launchToken->getInteractif(),
            $launchToken->getVisiteur(),
            $type,
            $payload,
            'launch:'.substr($launchToken->getToken(), 0, 16),
        );

        return $this->json([
            'accepted' => true,
            'logVisite' => (string) $log->getId(),
            'gamification' => $gamification,
        ], 202);
    }

    private function tokenError(?LaunchToken $token): ?JsonResponse
    {
        if (null === $token) {
            return $this->json(['error' => 'Jeton de lancement inconnu', 'code' => 'LIEN_INVALIDE'], 404);
        }
        if ($token->isExpired()) {
            return $this->json(['error' => 'Jeton de lancement expiré', 'code' => 'LIEN_EXPIRE'], 410);
        }

        return null;
    }
}
