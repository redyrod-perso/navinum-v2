<?php

namespace App\Service;

use App\Repository\LogVisiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class LogVisiteService
{
    public function __construct(
        private readonly LogVisiteRepository $logVisiteRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Calcule le total des scores avec différents filtres
     *
     * @param array $params Paramètres de filtre (visiteur_id, visite_id, interactif_id)
     * @return array{total: int}
     */
    public function getTotal(array $params): array
    {
        $qb = $this->logVisiteRepository->createQueryBuilder('lv')
            ->select('SUM(lv.score) as total');

        // Filtres possibles
        if (isset($params['visiteur_id'])) {
            $qb->andWhere('lv.visiteur = :visiteurId')
               ->setParameter('visiteurId', $params['visiteur_id'], 'uuid');
        }

        if (isset($params['visite_id'])) {
            $qb->andWhere('lv.visite = :visiteId')
               ->setParameter('visiteId', $params['visite_id'], 'uuid');
        }

        if (isset($params['interactif_id'])) {
            $qb->andWhere('lv.interactif = :interactifId')
               ->setParameter('interactifId', $params['interactif_id'], 'uuid');
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) ($result['total'] ?? 0)
        ];
    }

    /**
     * Retourne la liste des expositions visitées par un visiteur
     * avec leurs interactifs associés
     *
     * @param Uuid $visiteurId
     * @return array
     */
    public function getVisiteurExpositions(Uuid $visiteurId): array
    {
        $qb = $this->logVisiteRepository->createQueryBuilder('lv')
            ->select('e.id as exposition_id', 'i.id as interactif_id')
            ->addSelect('e.libelle', 'e.logo', 'e.description', 'e.synopsis')
            ->leftJoin('lv.exposition', 'e')
            ->leftJoin('lv.interactif', 'i')
            ->where('lv.visiteur = :visiteurId')
            ->andWhere('lv.exposition IS NOT NULL')
            ->andWhere('lv.interactif IS NOT NULL')
            ->groupBy('e.id', 'i.id', 'e.libelle', 'e.logo', 'e.description', 'e.synopsis')
            ->orderBy('e.id', 'ASC')
            ->setParameter('visiteurId', $visiteurId, 'uuid');

        $results = $qb->getQuery()->getResult();

        // Reformater les résultats en groupant par exposition
        $grouped = [];
        foreach ($results as $row) {
            $expositionId = (string) $row['exposition_id'];

            if (!isset($grouped[$expositionId])) {
                $grouped[$expositionId] = [
                    'exposition_id' => $expositionId,
                    'libelle' => $row['libelle'],
                    'logo' => $row['logo'],
                    'description' => $row['description'],
                    'synopsis' => $row['synopsis'],
                    'interactif_id' => []
                ];
            }

            $grouped[$expositionId]['interactif_id'][] = (string) $row['interactif_id'];
        }

        return array_values($grouped);
    }

    /**
     * Retourne la liste des interactifs joués par un visiteur
     * sur une ou plusieurs expositions
     *
     * @param Uuid $visiteurId
     * @param array $expositionIds Tableau d'UUIDs d'expositions
     * @return array Liste d'UUIDs d'interactifs
     */
    public function getVisiteurInteractifsExposition(Uuid $visiteurId, array $expositionIds): array
    {
        // Convert UUID objects to binary format for comparison
        $expositionIdBinary = array_map(fn($uuid) => $uuid->toBinary(), $expositionIds);

        $qb = $this->logVisiteRepository->createQueryBuilder('lv')
            ->select('i.id as interactif_id')
            ->leftJoin('lv.interactif', 'i')
            ->leftJoin('lv.exposition', 'e')
            ->where('lv.visiteur = :visiteurId')
            ->andWhere('e.id IN (:expositionIds)')
            ->andWhere('lv.interactif IS NOT NULL')
            ->groupBy('i.id')
            ->setParameter('visiteurId', $visiteurId, 'uuid')
            ->setParameter('expositionIds', $expositionIdBinary, \Doctrine\DBAL\ArrayParameterType::BINARY);

        $results = $qb->getQuery()->getResult();

        // Retourner uniquement les IDs des interactifs
        return array_map(fn($row) => (string) $row['interactif_id'], $results ?? []);
    }

    /**
     * Retourne le meilleur score (highscore) avec filtres optionnels
     *
     * @param Uuid|null $interactifId
     * @param Uuid|null $visiteurId
     * @param bool|null $isAnonyme
     * @return array|null
     */
    public function getHighScore(
        ?Uuid $interactifId = null,
        ?Uuid $visiteurId = null,
        ?bool $isAnonyme = null
    ): ?array {
        $qb = $this->logVisiteRepository->createQueryBuilder('lv')
            ->select('lv')
            ->addSelect('vis')
            ->leftJoin('lv.visiteur', 'vis')
            ->where('lv.visiteur IS NOT NULL')
            ->orderBy('lv.score', 'DESC')
            ->addOrderBy('lv.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($interactifId) {
            $qb->andWhere('lv.interactif = :interactifId')
               ->setParameter('interactifId', $interactifId, 'uuid');
        }

        if ($visiteurId) {
            $qb->andWhere('lv.visiteur = :visiteurId')
               ->setParameter('visiteurId', $visiteurId, 'uuid');
        }

        if ($isAnonyme !== null) {
            $qb->andWhere('vis.isAnonyme = :isAnonyme')
               ->setParameter('isAnonyme', $isAnonyme);
        }

        $results = $qb->getQuery()->getResult();

        if (empty($results)) {
            return null;
        }

        $logVisite = $results[0];
        $visiteur = $logVisite->getVisiteur();

        return [
            'highScore' => $logVisite->getScore(),
            'interactif_id' => $logVisite->getInteractif() ? (string) $logVisite->getInteractif()->getId() : null,
            'visiteur_id' => $visiteur ? (string) $visiteur->getId() : null,
            'start_at' => $logVisite->getStartAt(),
            'end_at' => $logVisite->getEndAt(),
            'visite_id' => $logVisite->getVisite() ? (string) $logVisite->getVisite()->getId() : null,
            'visiteur' => $visiteur
        ];
    }
}
