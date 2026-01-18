<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Typologie;
use App\Entity\Visiteur;
use App\Entity\Xp;
use Symfony\Component\Uid\Uuid;

class XpControllerTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean database before each test
        $em = static::getContainer()->get('doctrine')->getManager();
        $connection = $em->getConnection();

        // Disable foreign key checks temporarily
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        // Truncate tables
        $connection->executeStatement('TRUNCATE TABLE xp');
        $connection->executeStatement('TRUNCATE TABLE visiteur');
        $connection->executeStatement('TRUNCATE TABLE typologie');

        // Re-enable foreign key checks
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testGetTotalWithoutVisiteurId(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/xp/total');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le paramètre visiteur_id est requis'
        ]);
    }

    public function testGetTotalWithInvalidUuid(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/xp/total?visiteur_id=invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le visiteur_id doit être un UUID valide'
        ]);
    }

    public function testGetTotalSuccess(): void
    {
        $client = static::createClient();

        // Get EntityManager from the client's container
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Créer un visiteur
        $visiteur = new Visiteur();
        $em->persist($visiteur);

        // Créer une typologie
        $typologie = new Typologie();
        $typologie->setLibelle('Test Typologie ' . uniqid());
        $em->persist($typologie);

        // Créer des XP pour ce visiteur
        $xp1 = new Xp();
        $xp1->setScore(50);
        $xp1->setVisiteur($visiteur);
        $xp1->setTypologie($typologie);
        $em->persist($xp1);

        $xp2 = new Xp();
        $xp2->setScore(100);
        $xp2->setVisiteur($visiteur);
        $xp2->setTypologie($typologie);
        $em->persist($xp2);

        $em->flush();

        $client->request('GET', '/api/xp/total?visiteur_id=' . $visiteur->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            [
                'total' => 150
            ]
        ]);
    }

    public function testGetTotalBestVisiteurSuccess(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        // Créer deux visiteurs
        $visiteur1 = new Visiteur();
        $em->persist($visiteur1);

        $visiteur2 = new Visiteur();
        $em->persist($visiteur2);

        // Créer une typologie
        $typologie = new Typologie();
        $typologie->setLibelle('Test Typologie ' . uniqid());
        $em->persist($typologie);

        // Visiteur 1 : 200 points
        $xp1 = new Xp();
        $xp1->setScore(100);
        $xp1->setVisiteur($visiteur1);
        $xp1->setTypologie($typologie);
        $em->persist($xp1);

        $xp2 = new Xp();
        $xp2->setScore(100);
        $xp2->setVisiteur($visiteur1);
        $xp2->setTypologie($typologie);
        $em->persist($xp2);

        // Visiteur 2 : 50 points
        $xp3 = new Xp();
        $xp3->setScore(50);
        $xp3->setVisiteur($visiteur2);
        $xp3->setTypologie($typologie);
        $em->persist($xp3);

        $em->flush();

        $client->request('GET', '/api/xp/totalBestVisiteur');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals(200, $data[0]['total']);
        $this->assertEquals($visiteur1->getId()->__toString(), $data[0]['visiteur_id']);
    }

    public function testGetTotalBestVisiteurWhenNoXp(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/xp/totalBestVisiteur');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([]);
    }
}
