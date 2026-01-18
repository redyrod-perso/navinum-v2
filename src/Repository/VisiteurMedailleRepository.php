<?php

namespace App\Repository;

use App\Entity\VisiteurMedaille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VisiteurMedaille>
 */
class VisiteurMedailleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VisiteurMedaille::class);
    }
}
