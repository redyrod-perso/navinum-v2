<?php

namespace App\Controller\Admin;

use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\LogVisite;
use App\Entity\Parcours;
use App\Entity\Visiteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ORGANISATEUR')]
final class DashboardController extends AbstractController
{
    use OrganisateurScopeTrait;

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): Response
    {
        $count = fn (string $class, ?callable $scope = null) => (int) ($scope ? $scope($em->getRepository($class)->createQueryBuilder('e')) : $em->getRepository($class)->createQueryBuilder('e'))
            ->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        $stats = [
            'expositions' => $count(Exposition::class, fn ($qb) => $this->scopeExpositions($qb, 'e')),
            'parcours' => $count(Parcours::class, fn ($qb) => $this->isFullAdmin() ? $qb : $this->scopeExpositions($qb->innerJoin('e.expositions', 'x'), 'x')),
        ];
        if ($this->isFullAdmin()) {
            $stats['interactifs'] = $count(Interactif::class);
            $stats['visiteurs'] = $count(Visiteur::class);
            $stats['visites'] = $count(LogVisite::class);
        }

        $recent = $this->isFullAdmin()
            ? $em->getRepository(LogVisite::class)->findBy([], ['start_at' => 'DESC'], 10)
            : [];

        return $this->render('admin/dashboard.html.twig', ['stats' => $stats, 'recent' => $recent]);
    }
}
