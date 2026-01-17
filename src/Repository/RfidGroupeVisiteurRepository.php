<?php

namespace App\Repository;

use App\Entity\RfidGroupeVisiteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RfidGroupeVisiteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RfidGroupeVisiteur::class);
    }
}

