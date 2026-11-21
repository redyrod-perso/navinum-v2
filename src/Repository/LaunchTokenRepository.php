<?php

namespace App\Repository;

use App\Entity\LaunchToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LaunchToken>
 */
class LaunchTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LaunchToken::class);
    }

    public function findOneByToken(string $token): ?LaunchToken
    {
        return $this->findOneBy(['token' => $token]);
    }
}
