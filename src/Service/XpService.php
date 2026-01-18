<?php

namespace App\Service;

use App\Repository\XpRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class XpService
{
    public function __construct(
        private readonly XpRepository $xpRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule le total des XP d'un visiteur
     *
     * @param Uuid $visiteurId
     * @return array{total: int}
     */
    public function getTotalByVisiteur(Uuid $visiteurId): array
    {
        $qb = $this->xpRepository->createQueryBuilder('x')
            ->select('SUM(x.score) as total')
            ->where('x.visiteur = :visiteurId')
            ->setParameter('visiteurId', $visiteurId, 'uuid');

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) ($result['total'] ?? 0)
        ];
    }

    /**
     * Retourne le visiteur avec le meilleur score total
     *
     * @return array{total: int, visiteur_id: string}|null
     */
    public function getTotalBestVisiteur(): ?array
    {
        $query = $this->entityManager->createQuery(
            'SELECT SUM(x.score) as total, v.id as visiteur_id
             FROM App\Entity\Xp x
             JOIN x.visiteur v
             GROUP BY v.id
             ORDER BY total DESC'
        );

        $query->setMaxResults(1);

        $results = $query->getResult();

        if (empty($results)) {
            return null;
        }

        return [
            'total' => (int) $results[0]['total'],
            'visiteur_id' => (string) $results[0]['visiteur_id']
        ];
    }
}
