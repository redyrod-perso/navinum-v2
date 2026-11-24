<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Typologie;
use App\Entity\Visiteur;
use App\Entity\Xp;
use App\Tests\Traits\DatabaseTestTrait;
use Symfony\Component\Uid\Uuid;

/** Totaux d'XP de l'administration : réservés aux comptes administrateurs (espace admin). */
class XpControllerTest extends ApiTestCase
{
    use DatabaseTestTrait;

    // Comportement explicite (défaut futur d'API Platform 5) : le noyau démarre avec chaque client de test
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean database before each test
        $em = static::getContainer()->get('doctrine')->getManager();
        $connection = $em->getConnection();

        // Vide les tables (PostgreSQL : CASCADE gère les clés étrangères)
        $connection->executeStatement('TRUNCATE TABLE xp, visiteur, typologie CASCADE');
    }

    public function testGetTotalWithoutVisiteurId(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/admin/api/xp/total');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le paramètre visiteur_id est requis'
        ]);
    }

    public function testGetTotalWithInvalidUuid(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/admin/api/xp/total?visiteur_id=invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'error' => 'Le visiteur_id doit être un UUID valide'
        ]);
    }

    public function testGetTotalSuccess(): void
    {
        $client = static::createClient();
        $this->login($client);

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

        $client->request('GET', '/admin/api/xp/total?visiteur_id=' . $visiteur->getId());

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
        $this->login($client);
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

        $client->request('GET', '/admin/api/xp/totalBestVisiteur');

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
        $this->login($client);

        $client->request('GET', '/admin/api/xp/totalBestVisiteur');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([]);
    }

    /** Plus de totaux d'XP accessibles sans compte administrateur. */
    public function testReserveAuxAdministrateurs(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/xp/totalBestVisiteur');
        $this->assertResponseStatusCodeSame(404, 'ancienne adresse publique supprimée');

        $client->request('GET', '/admin/api/xp/totalBestVisiteur');
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', (string) $client->getResponse()->getHeaders(false)['location'][0]);

        $this->login($client, \App\Entity\User::ROLE_ORGANISATEUR);
        $client->request('GET', '/admin/api/xp/totalBestVisiteur');
        $this->assertResponseStatusCodeSame(403);
    }
}
