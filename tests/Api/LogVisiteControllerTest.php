<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\LogVisite;
use App\Entity\Visiteur;

class LogVisiteControllerTest extends ApiTestCase
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
        $connection->executeStatement('TRUNCATE TABLE log_visite');
        $connection->executeStatement('TRUNCATE TABLE visiteur');
        $connection->executeStatement('TRUNCATE TABLE interactif');
        $connection->executeStatement('TRUNCATE TABLE exposition');

        // Re-enable foreign key checks
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function testGetTotalWithoutParams(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/log_visite/total');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Au moins un paramètre (visiteur_id, visite_id ou interactif_id) est requis'
        ]);
    }

    public function testGetTotalWithInvalidVisiteurId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/log_visite/total?visiteur_id=invalid');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le visiteur_id doit être un UUID valide'
        ]);
    }

    public function testGetTotalSuccess(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Créer un visiteur
        $visiteur = new Visiteur();
        $em->persist($visiteur);

        // Créer une exposition
        $exposition = new Exposition();
        $exposition->setLibelle('Expo Test ' . uniqid());
        $em->persist($exposition);

        // Créer un interactif
        $interactif = new Interactif();
        $interactif->setLibelle('Interactif Test ' . uniqid());
        $em->persist($interactif);

        // Créer des logs de visite
        $log1 = new LogVisite();
        $log1->setVisiteur($visiteur);
        $log1->setExposition($exposition);
        $log1->setInteractif($interactif);
        $log1->setScore(100);
        $log1->setStartAt(new \DateTime());
        $em->persist($log1);

        $log2 = new LogVisite();
        $log2->setVisiteur($visiteur);
        $log2->setExposition($exposition);
        $log2->setInteractif($interactif);
        $log2->setScore(50);
        $log2->setStartAt(new \DateTime());
        $em->persist($log2);

        $em->flush();

        $client->request('GET', '/api/log_visite/total?visiteur_id=' . $visiteur->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            [
                'total' => 150
            ]
        ]);
    }

    public function testVisiteurExpositionsWithoutVisiteurId(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/log_visite/visiteurExpositions');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le paramètre visiteur_id est requis'
        ]);
    }

    public function testVisiteurExpositionsSuccess(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Créer un visiteur
        $visiteur = new Visiteur();
        $em->persist($visiteur);

        // Créer deux expositions
        $exposition1 = new Exposition();
        $exposition1->setLibelle('Expo 1 ' . uniqid());
        $em->persist($exposition1);

        $exposition2 = new Exposition();
        $exposition2->setLibelle('Expo 2 ' . uniqid());
        $em->persist($exposition2);

        // Créer des interactifs
        $interactif1 = new Interactif();
        $interactif1->setLibelle('Interactif 1 ' . uniqid());
        $em->persist($interactif1);

        $interactif2 = new Interactif();
        $interactif2->setLibelle('Interactif 2 ' . uniqid());
        $em->persist($interactif2);

        // Logs: Expo1 avec interactif1 et interactif2, Expo2 avec interactif1
        $log1 = new LogVisite();
        $log1->setVisiteur($visiteur);
        $log1->setExposition($exposition1);
        $log1->setInteractif($interactif1);
        $log1->setScore(100);
        $log1->setStartAt(new \DateTime());
        $em->persist($log1);

        $log2 = new LogVisite();
        $log2->setVisiteur($visiteur);
        $log2->setExposition($exposition1);
        $log2->setInteractif($interactif2);
        $log2->setScore(50);
        $log2->setStartAt(new \DateTime());
        $em->persist($log2);

        $log3 = new LogVisite();
        $log3->setVisiteur($visiteur);
        $log3->setExposition($exposition2);
        $log3->setInteractif($interactif1);
        $log3->setScore(75);
        $log3->setStartAt(new \DateTime());
        $em->persist($log3);

        $em->flush();

        $client->request('GET', '/api/log_visite/visiteurExpositions?visiteur_id=' . $visiteur->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertCount(2, $data); // 2 expositions

        // Vérifier la structure
        $this->assertArrayHasKey('exposition_id', $data[0]);
        $this->assertArrayHasKey('libelle', $data[0]);
        $this->assertArrayHasKey('interactif_id', $data[0]);
        $this->assertIsArray($data[0]['interactif_id']);
    }

    public function testVisiteurInteractifsExpositionWithoutParams(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/log_visite/visiteurInteractifsExposition');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testVisiteurInteractifsExpositionSuccess(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Créer un visiteur
        $visiteur = new Visiteur();
        $em->persist($visiteur);

        // Créer une exposition
        $exposition = new Exposition();
        $exposition->setLibelle('Expo Test ' . uniqid());
        $em->persist($exposition);

        // Créer deux interactifs
        $interactif1 = new Interactif();
        $interactif1->setLibelle('Interactif 1 ' . uniqid());
        $em->persist($interactif1);

        $interactif2 = new Interactif();
        $interactif2->setLibelle('Interactif 2 ' . uniqid());
        $em->persist($interactif2);

        // Créer des logs
        $log1 = new LogVisite();
        $log1->setVisiteur($visiteur);
        $log1->setExposition($exposition);
        $log1->setInteractif($interactif1);
        $log1->setScore(100);
        $log1->setStartAt(new \DateTime());
        $em->persist($log1);

        $log2 = new LogVisite();
        $log2->setVisiteur($visiteur);
        $log2->setExposition($exposition);
        $log2->setInteractif($interactif2);
        $log2->setScore(50);
        $log2->setStartAt(new \DateTime());
        $em->persist($log2);

        $em->flush();

        $client->request('GET', '/api/log_visite/visiteurInteractifsExposition?visiteur_id=' . $visiteur->getId() . '&exposition_id=' . $exposition->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertCount(2, $data); // 2 interactifs
        $this->assertEquals($interactif1->getId()->__toString(), $data[0]);
        $this->assertEquals($interactif2->getId()->__toString(), $data[1]);
    }

    public function testHighScoreSuccess(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine')->getManager();

        // Créer des visiteurs
        $visiteur1 = new Visiteur();
        $em->persist($visiteur1);

        $visiteur2 = new Visiteur();
        $em->persist($visiteur2);

        // Créer une exposition
        $exposition = new Exposition();
        $exposition->setLibelle('Expo Test ' . uniqid());
        $em->persist($exposition);

        // Créer un interactif
        $interactif = new Interactif();
        $interactif->setLibelle('Interactif Test ' . uniqid());
        $em->persist($interactif);

        // Créer des logs avec différents scores
        $log1 = new LogVisite();
        $log1->setVisiteur($visiteur1);
        $log1->setExposition($exposition);
        $log1->setInteractif($interactif);
        $log1->setScore(500); // Meilleur score
        $log1->setStartAt(new \DateTime());
        $em->persist($log1);

        $log2 = new LogVisite();
        $log2->setVisiteur($visiteur2);
        $log2->setExposition($exposition);
        $log2->setInteractif($interactif);
        $log2->setScore(300);
        $log2->setStartAt(new \DateTime());
        $em->persist($log2);

        $em->flush();

        $client->request('GET', '/api/log_visite/highScore');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals(500, $data[0]['highScore']);
        $this->assertEquals($visiteur1->getId()->__toString(), $data[0]['visiteur_id']);
    }

    public function testHighScoreWhenNoData(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/log_visite/highScore');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([]);
    }
}
