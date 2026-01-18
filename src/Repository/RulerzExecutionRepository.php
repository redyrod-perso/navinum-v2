<?php

namespace App\Repository;

use App\Entity\RulerzExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RulerzExecution>
 */
class RulerzExecutionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RulerzExecution::class);
    }
}
