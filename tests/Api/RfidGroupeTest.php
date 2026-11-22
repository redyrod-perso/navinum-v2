<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RfidGroupe;

class RfidGroupeTest extends ApiTestCase
{
    // Comportement explicite (défaut futur d'API Platform 5) : le noyau démarre avec chaque client de test
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Repartir d'une table vide : les noms de groupe sont uniques
        static::getContainer()->get('doctrine')->getManager()->getConnection()
            ->executeStatement('TRUNCATE TABLE rfid_groupe CASCADE');
    }

    public function testGetCollection(): void
    {
        // Créer un groupe de test
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        $groupe = new RfidGroupe();
        $groupe->setNom('Test Groupe API');
        $em->persist($groupe);
        $em->flush();

        // Tester l'API
        $response = $client->request('GET', '/api/rfid_groupes', [
            'headers' => ['Accept' => 'application/ld+json']
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith('application/ld+json', (string) static::getClient()->getResponse()->headers->get('content-type'));

        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(1, $data['member']);
        $this->assertEquals('Test Groupe API', $data['member'][0]['nom']);
    }

    public function testCreateGroupe(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Nouveau Groupe Test'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertStringStartsWith('application/ld+json', (string) static::getClient()->getResponse()->headers->get('content-type'));

        $data = $response->toArray();
        $this->assertArrayHasKey('nom', $data);
        $this->assertEquals('Nouveau Groupe Test', $data['nom']);
    }
}
