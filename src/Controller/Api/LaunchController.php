<?php

namespace App\Controller\Api;

use App\Entity\LaunchToken;
use App\Gamification\GamificationService;
use App\Gamification\MedailleRefusee;
use App\Gamification\SessionScoreVerifier;
use App\Repository\LaunchTokenRepository;
use App\Service\LaunchService;
use App\Service\VisiteurToken;
use App\Webhook\Webhooks;
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
 * - GET  /api/launch/{token}/medailles catalogue des médailles de l'interactif (obtenues ou non par le joueur)
 * - POST /api/launch/{token}/medailles médaille demandée par l'interactif {medaille} (visiteur rattaché requis,
 *                                    médaille listée dans `attribuables` des règles de l'interactif)
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
        private readonly SessionScoreVerifier $scores,
        private readonly Webhooks $webhooks,
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

        // Fin de partie multijoueur : score repris de la session contrôlée par le serveur (règle scoreSession)
        $payload = $this->scores->verifier($launchToken, $type, $payload);

        // Gains et journal de visite : jamais au profit (ni au nom) d'un autre joueur que celui qui joue
        $rattache = $this->joueurRattache($launchToken, $request);
        $log = $this->launch->recordEvent(
            $launchToken,
            $type,
            $payload,
            $rattache ? null : ['visiteur' => $this->visiteurTokens->fromRequest($request)],
        );

        // Gamification : XP et médailles selon les règles de l'interactif (null sans visiteur identifié)
        $gamification = $this->gamification->apply(
            $launchToken->getInteractif(),
            $rattache ? $launchToken->getVisiteur() : null,
            $type,
            $payload,
            'launch:'.substr($launchToken->getToken(), 0, 16),
        );

        // Webhook « partie.terminee » (#10) : fin de partie, avec le score contrôlé et les gains
        if (str_ends_with($type, '.completed')) {
            $this->webhooks->publier('partie.terminee', [
                'evenement' => $type,
                'interactif' => $launchToken->getInteractif()->getLibelle(),
                'parcours' => $launchToken->getParcoursInteractif()?->getParcours()?->getLibelle(),
                'exposition' => $launchToken->getExposition()?->getLibelle(),
                'donnees' => $payload,
                'visiteur' => Webhooks::visiteur($rattache ? $launchToken->getVisiteur() : null),
                'gains' => $gamification,
            ]);
        }

        return $this->json([
            'accepted' => true,
            'logVisite' => (string) $log->getId(),
            'gamification' => $gamification,
            'rattache' => $rattache,
        ], 202);
    }

    /**
     * Médaille demandée par l'interactif pour le visiteur de ce lancement.
     * 200 : `{ medaille, nouvelle, totalXp }` (nouvelle = false si déjà obtenue : appel idempotent) ;
     * 401 NON_IDENTIFIE (aucun visiteur rattaché) ; 403 MEDAILLE_NON_ATTRIBUABLE ; 404 MEDAILLE_INCONNUE.
     */
    /**
     * Médailles de l'interactif lancé (catalogue à afficher au joueur) ; `obtenue` pour le joueur du jeton
     * Bearer, à défaut le visiteur rattaché au lancement (null sans joueur identifié).
     */
    #[Route('/medailles', name: 'medailles', methods: ['GET'])]
    public function medailles(string $token, Request $request): JsonResponse
    {
        $launchToken = $this->tokens->findOneByToken($token);
        if ($error = $this->tokenError($launchToken)) {
            return $error;
        }
        $visiteur = null !== VisiteurToken::bearer($request) ? $this->visiteurTokens->fromRequest($request) : $launchToken->getVisiteur();

        return $this->json(['medailles' => $this->gamification->medaillesDe($launchToken->getInteractif(), $visiteur)]);
    }

    #[Route('/medailles', name: 'medaille', methods: ['POST'])]
    public function medaille(string $token, Request $request): JsonResponse
    {
        $launchToken = $this->tokens->findOneByToken($token);
        if ($error = $this->tokenError($launchToken)) {
            return $error;
        }
        $visiteur = $launchToken->getVisiteur();
        if (null === $visiteur) {
            return $this->json(['error' => 'Identifiez le joueur avant de lui attribuer une médaille', 'code' => 'NON_IDENTIFIE'], 401);
        }
        if (!$this->joueurRattache($launchToken, $request)) {
            return $this->json(['error' => 'Ce lancement est rattaché à un autre joueur : la médaille ne peut pas vous être attribuée', 'code' => 'DEJA_RATTACHE'], 409);
        }
        $body = json_decode($request->getContent(), true);
        $libelle = \is_array($body) && \is_string($body['medaille'] ?? null) ? trim($body['medaille']) : '';
        if ('' === $libelle || mb_strlen($libelle) > 255) {
            return $this->json(['error' => 'Champ "medaille" obligatoire (libellé de la médaille)', 'code' => 'VALIDATION'], 400);
        }

        try {
            $result = $this->gamification->demanderMedaille(
                $launchToken->getInteractif(),
                $visiteur,
                $libelle,
                'launch:'.substr($launchToken->getToken(), 0, 16),
            );
        } catch (MedailleRefusee $e) {
            return $this->json(['error' => $e->getMessage(), 'code' => $e->codeMetier], 'MEDAILLE_INCONNUE' === $e->codeMetier ? 404 : 403);
        }

        // Trace dans le parcours du visiteur (et le fil en direct de l'admin)
        $this->launch->recordEvent($launchToken, 'navinum.medaille', ['medaille' => $libelle, 'nouvelle' => $result['nouvelle']]);

        return $this->json($result);
    }

    /**
     * Les gains vont-ils bien au joueur qui joue ? Si la requête porte un jeton visiteur (Bearer), il doit
     * désigner le visiteur rattaché au lancement : sinon (lien réutilisé par un autre joueur alors que le
     * précédent est encore actif), personne n'est crédité. Sans jeton : le visiteur rattaché (lancement
     * préparé par Navinum pour ce visiteur).
     */
    private function joueurRattache(LaunchToken $launchToken, Request $request): bool
    {
        if (null === VisiteurToken::bearer($request)) {
            return true;
        }
        $joueur = $this->visiteurTokens->fromRequest($request);
        $visiteur = $launchToken->getVisiteur();

        return null !== $joueur && null !== $visiteur && $joueur->getId()->equals($visiteur->getId());
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
