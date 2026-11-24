<?php

namespace App\Controller\Api;

use App\Entity\CleIntegration;
use App\Entity\LogVisite;
use App\Entity\Visiteur;
use App\Entity\VisiteurMedaille;
use App\Entity\Xp;
use App\Gamification\GamificationService;
use App\Gamification\MedailleRefusee;
use App\Security\IntegrationUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * API des intégrations (n8n…, #11) : `Authorization: Bearer nvk1.…` (clé créée dans Admin › Intégrations).
 *
 * - GET  /api/integration/moi          → la clé utilisée (libellé, droits) : test de connexion
 * - GET  /api/integration/statistiques → chiffres globaux (droit « statistiques »)
 * - POST /api/integration/medailles {visiteur, medaille} → attribue une médaille existante (droit « medailles »),
 *        sans doublon (médaille unique : une fois par visiteur ; autre : une fois par clé)
 *
 * Refus : 401 `NON_IDENTIFIE` (clé absente, inconnue ou révoquée), 403 (droit manquant), 404 `MEDAILLE_INCONNUE`,
 * `VISITEUR_INCONNU`, 400 `VALIDATION`.
 */
#[Route('/api/integration', name: 'api_integration_')]
#[IsGranted('ROLE_INTEGRATION')]
final class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GamificationService $gamification,
    ) {
    }

    #[Route('/moi', name: 'moi', methods: ['GET'])]
    public function moi(): JsonResponse
    {
        $cle = $this->cle();

        return $this->json(['libelle' => $cle->getLibelle(), 'droits' => $cle->getDroits()]);
    }

    #[Route('/statistiques', name: 'statistiques', methods: ['GET'])]
    #[IsGranted('ROLE_INTEGRATION_STATISTIQUES')]
    public function statistiques(): JsonResponse
    {
        $compter = fn (string $classe) => (int) $this->em->getRepository($classe)->count([]);

        return $this->json([
            'visiteurs' => $compter(Visiteur::class),
            'comptes' => (int) $this->em->getRepository(Visiteur::class)->count(['type' => 'compte']),
            'visites' => $compter(LogVisite::class),
            'medaillesObtenues' => $compter(VisiteurMedaille::class),
            'xpTotal' => (int) $this->em->createQuery('SELECT COALESCE(SUM(x.score), 0) FROM '.Xp::class.' x')->getSingleScalarResult(),
        ]);
    }

    #[Route('/medailles', name: 'medailles', methods: ['POST'])]
    #[IsGranted('ROLE_INTEGRATION_MEDAILLES')]
    public function medailles(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = \is_array($data) ? (string) ($data['visiteur'] ?? '') : '';
        $libelle = \is_array($data) && \is_string($data['medaille'] ?? null) ? trim($data['medaille']) : '';
        if (!Uuid::isValid($id) || '' === $libelle) {
            return new JsonResponse(['error' => 'Champs "visiteur" (identifiant) et "medaille" (libellé) obligatoires', 'code' => 'VALIDATION'], 400);
        }
        $visiteur = $this->em->getRepository(Visiteur::class)->find(Uuid::fromString($id));
        if (null === $visiteur || !$visiteur->isActive()) {
            return new JsonResponse(['error' => 'Visiteur inconnu', 'code' => 'VISITEUR_INCONNU'], 404);
        }

        try {
            $resultat = $this->gamification->attribuer($visiteur, $libelle, 'integration:'.$this->cle()->getId()->toRfc4122());
        } catch (MedailleRefusee $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'code' => $e->codeMetier], 404);
        }

        return $this->json($resultat);
    }

    private function cle(): CleIntegration
    {
        $user = $this->getUser();
        \assert($user instanceof IntegrationUser);

        return $user->getCle();
    }
}
