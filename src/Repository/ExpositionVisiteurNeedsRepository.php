<?php

namespace App\Repository;

use App\Entity\ExpositionVisiteurNeeds;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExpositionVisiteurNeeds>
 */
class ExpositionVisiteurNeedsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExpositionVisiteurNeeds::class);
    }
}
