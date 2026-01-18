<?php

namespace App\Controller\Api;

use App\Service\LogVisiteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/log_visite', name: 'api_log_visite_')]
class LogVisiteController extends AbstractController
{
    public function __construct(
        private readonly LogVisiteService $logVisiteService
    ) {
    }

    #[Route('/total', name: 'total', methods: ['GET'])]
    public function total(Request $request): JsonResponse
    {
        $params = [];

        // Récupérer et valider les paramètres
        if ($visiteurId = $request->query->get('visiteur_id')) {
            try {
                $params['visiteur_id'] = Uuid::fromString($visiteurId);
            } catch (\InvalidArgumentException $e) {
                return $this->json(
                    ['error' => 'Le visiteur_id doit être un UUID valide'],
                    400
                );
            }
        }

        if ($visiteId = $request->query->get('visite_id')) {
            try {
                $params['visite_id'] = Uuid::fromString($visiteId);
            } catch (\InvalidArgumentException $e) {
                return $this->json(
                    ['error' => 'Le visite_id doit être un UUID valide'],
                    400
                );
            }
        }

        if ($interactifId = $request->query->get('interactif_id')) {
            try {
                $params['interactif_id'] = Uuid::fromString($interactifId);
            } catch (\InvalidArgumentException $e) {
                return $this->json(
                    ['error' => 'L\'interactif_id doit être un UUID valide'],
                    400
                );
            }
        }

        // Au moins un paramètre est requis
        if (empty($params)) {
            return $this->json(
                ['error' => 'Au moins un paramètre (visiteur_id, visite_id ou interactif_id) est requis'],
                400
            );
        }

        $result = $this->logVisiteService->getTotal($params);

        return $this->json([$result]);
    }

    #[Route('/visiteurExpositions', name: 'visiteur_expositions', methods: ['GET'])]
    public function visiteurExpositions(Request $request): JsonResponse
    {
        $visiteurId = $request->query->get('visiteur_id');

        if (!$visiteurId) {
            return $this->json(
                ['error' => 'Le paramètre visiteur_id est requis'],
                400
            );
        }

        try {
            $uuid = Uuid::fromString($visiteurId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => 'Le visiteur_id doit être un UUID valide'],
                400
            );
        }

        $result = $this->logVisiteService->getVisiteurExpositions($uuid);

        return $this->json($result);
    }

    #[Route('/visiteurInteractifsExposition', name: 'visiteur_interactifs_exposition', methods: ['GET'])]
    public function visiteurInteractifsExposition(Request $request): JsonResponse
    {
        $visiteurId = $request->query->get('visiteur_id');
        $expositionId = $request->query->get('exposition_id');

        if (!$visiteurId) {
            return $this->json(
                ['error' => 'Le paramètre visiteur_id est requis'],
                400
            );
        }

        if (!$expositionId) {
            return $this->json(
                ['error' => 'Le paramètre exposition_id est requis'],
                400
            );
        }

        try {
            $visiteurUuid = Uuid::fromString($visiteurId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => 'Le visiteur_id doit être un UUID valide'],
                400
            );
        }

        // exposition_id peut être une seule valeur ou un tableau (comme dans SF1)
        $expositionIds = is_array($expositionId) ? $expositionId : [$expositionId];

        // Valider chaque UUID d'exposition
        try {
            $expositionUuids = array_map(
                fn($id) => Uuid::fromString($id),
                $expositionIds
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => 'Tous les exposition_id doivent être des UUIDs valides'],
                400
            );
        }

        $result = $this->logVisiteService->getVisiteurInteractifsExposition(
            $visiteurUuid,
            $expositionUuids
        );

        return $this->json($result);
    }

    #[Route('/highScore', name: 'high_score', methods: ['GET'])]
    public function highScore(Request $request): JsonResponse
    {
        $interactifId = null;
        $visiteurId = null;
        $isAnonyme = null;

        // Paramètres optionnels
        if ($interactifIdStr = $request->query->get('interactif_id')) {
            try {
                $interactifId = Uuid::fromString($interactifIdStr);
            } catch (\InvalidArgumentException $e) {
                return $this->json(
                    ['error' => 'L\'interactif_id doit être un UUID valide'],
                    400
                );
            }
        }

        if ($visiteurIdStr = $request->query->get('visiteur_id')) {
            try {
                $visiteurId = Uuid::fromString($visiteurIdStr);
            } catch (\InvalidArgumentException $e) {
                return $this->json(
                    ['error' => 'Le visiteur_id doit être un UUID valide'],
                    400
                );
            }
        }

        if ($request->query->has('is_anonyme')) {
            $isAnonyme = filter_var(
                $request->query->get('is_anonyme'),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }

        $result = $this->logVisiteService->getHighScore($interactifId, $visiteurId, $isAnonyme);

        if ($result === null) {
            return $this->json([]);
        }

        return $this->json([$result]);
    }
}
