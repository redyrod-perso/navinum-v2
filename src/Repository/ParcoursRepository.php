<?php

namespace App\Repository;

use App\Entity\Parcours;
use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class ParcoursRepository extends EntityRepository
{

    /**
     * QueryBuilder optimisé pour la grille d'administration Sylius.
     * Précharge les relations pour éviter les requêtes N+1.
     */
    public function createParcoursGridQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.expositions', 'e')
            ->leftJoin('p.interactifs', 'i')
            ->addSelect('e', 'i')
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC');
    }

    /**
     * Trouve tous les parcours actifs pour l'API publique.
     */
    public function findActiveParcoursForApi(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.expositions', 'e')
            ->leftJoin('p.interactifs', 'i')
            ->addSelect('e', 'i')
            ->where('p.is_tosync = :active')
            ->setParameter('active', true)
            ->orderBy('p.ordre', 'ASC')
            ->addOrderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un libellé est déjà utilisé (pour la validation d'unicité).
     */
    public function isLibelleUnique(string $libelle, ?Parcours $excludeParcours = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.libelle = :libelle')
            ->setParameter('libelle', $libelle);

        if ($excludeParcours) {
            $qb->andWhere('p.id != :excludeId')
               ->setParameter('excludeId', $excludeParcours->getId());
        }

        return $qb->getQuery()->getOneOrNullResult() === null;
    }

    /**
     * Trouve le prochain ordre disponible pour un nouveau parcours.
     */
    public function findNextAvailableOrdre(): int
    {
        $result = $this->createQueryBuilder('p')
            ->select('MAX(p.ordre) as maxOrdre')
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }
}

