<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RfidGroupe;

class RfidGroupeTest extends ApiTestCase
{

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
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('hydra:member', $data);
        $this->assertCount(1, $data['hydra:member']);
        $this->assertEquals('Test Groupe API', $data['hydra:member'][0]['nom']);
    }

    public function testCreateGroupe(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/rfid_groupes', [
            'json' => [
                'nom' => 'Nouveau Groupe Test'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('nom', $data);
        $this->assertEquals('Nouveau Groupe Test', $data['nom']);
    }
}
