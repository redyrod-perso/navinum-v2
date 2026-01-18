<?php

namespace App\Controller\Api;

use App\Service\XpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/xp', name: 'api_xp_')]
class XpController extends AbstractController
{
    public function __construct(
        private readonly XpService $xpService
    ) {
    }

    #[Route('/total', name: 'total', methods: ['GET'])]
    public function total(Request $request): JsonResponse
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

        $result = $this->xpService->getTotalByVisiteur($uuid);

        return $this->json([$result]);
    }

    #[Route('/totalBestVisiteur', name: 'total_best_visiteur', methods: ['GET'])]
    public function totalBestVisiteur(): JsonResponse
    {
        $result = $this->xpService->getTotalBestVisiteur();

        if ($result === null) {
            return $this->json([]);
        }

        return $this->json([$result]);
    }
}
