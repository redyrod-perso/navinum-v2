<?php

namespace App\Tests\Traits;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait to help with database cleanup in functional tests
 */
trait DatabaseTestTrait
{
    /**
     * Clear all data from an entity table
     * Must be called after createClient() has been called
     */
    protected function clearTable($client, string $entityClass): void
    {
        $container = $client->getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $connection = $em->getConnection();
        $tableName = $em->getClassMetadata($entityClass)->getTableName();

        // Disable foreign key checks for SQLite
        $platform = $connection->getDatabasePlatform()->getName();
        if ($platform === 'sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        }

        $connection->executeStatement("DELETE FROM {$tableName}");

        if ($platform === 'sqlite') {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Connecte un compte d'admin (créé à la volée) sur le client de test.
     * $role : User::ROLE_ADMIN ou User::ROLE_ORGANISATEUR.
     */
    protected function login($client, string $role = \App\Entity\User::ROLE_ADMIN, ?\App\Entity\Organisateur $organisateur = null): \App\Entity\User
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $email = strtolower(str_replace('ROLE_', '', $role)).'-'.bin2hex(random_bytes(4)).'@test.local';
        $user = (new \App\Entity\User())
            ->setEmail($email)
            ->setRoles([$role])
            ->setOrganisateur($organisateur)
            ->setPassword('test-non-utilise');
        $em->persist($user);
        $em->flush();
        $client->loginUser($user);

        return $user;
    }
}
