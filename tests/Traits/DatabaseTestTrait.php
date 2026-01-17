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
     * Login helper for tests
     */
    protected function login($client): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);
    }
}
