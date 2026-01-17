<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RfidGroupe;
use Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait;

class RfidGroupeApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Nettoyer la table rfid_groupe avant chaque test
        $kernel = self::bootKernel();
        $em = $kernel->getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\RfidGroupe')->execute();
    }

    /**
     * Test GET collection vide
     */
    public function testGetCollection(): void
    {
        $response = static::createClient()->request('GET', '/api/rfid_groupes');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/api/contexts/RfidGroupe',
            '@id' => '/api/rfid_groupes',
            '@type' => 'Collection',
        ]);

        // Vérifier que c'est bien une collection
        $this->assertMatchesResourceCollectionJsonSchema(RfidGroupe::class);
    }

    /**
     * Test POST création d'un groupe RFID valide
     */
    public function testCreateRfidGroupe(): void
    {
        $response = static::createClient()->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Scolaire A',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/api/contexts/RfidGroupe',
            '@type' => 'RfidGroupe',
            'nom' => 'Groupe Scolaire A',
        ]);

        // Vérifier que l'ID a été généré
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertNotEmpty($data['id']);

        // Vérifier le schema JSON
        $this->assertMatchesResourceItemJsonSchema(RfidGroupe::class);
    }

    /**
     * Test POST avec nom vide (violation NotBlank)
     */
    public function testCreateRfidGroupeWithEmptyName(): void
    {
        static::createClient()->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');

        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
        ]);
    }

    /**
     * Test POST sans nom (violation NotBlank)
     */
    public function testCreateRfidGroupeWithoutName(): void
    {
        static::createClient()->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');

        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
        ]);
    }

    /**
     * Test POST avec nom en double (violation UniqueEntity)
     */
    public function testCreateRfidGroupeWithDuplicateName(): void
    {
        $client = static::createClient();

        // Créer le premier groupe
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Unique',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Essayer de créer un groupe avec le même nom
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Unique',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
        ]);
    }

    /**
     * Test POST avec nom trop long (> 255 caractères)
     */
    public function testCreateRfidGroupeWithTooLongName(): void
    {
        static::createClient()->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => str_repeat('a', 256),
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
        ]);
    }

    /**
     * Test GET d'un groupe spécifique
     */
    public function testGetRfidGroupe(): void
    {
        $client = static::createClient();

        // Créer un groupe
        $response = $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Test Get',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $id = $data['id'];

        // Récupérer le groupe
        $client->request('GET', '/api/rfid_groupes/' . $id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'RfidGroupeOutput',
            'id' => $id,
            'nom' => 'Groupe Test Get',
        ]);

        $data = $client->getResponse()->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nom', $data);
    }

    /**
     * Test GET d'un groupe inexistant
     */
    public function testGetNonExistentRfidGroupe(): void
    {
        static::createClient()->request('GET', '/api/rfid_groupes/019bcc7d-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test PUT pour mettre à jour un groupe
     */
    public function testUpdateRfidGroupe(): void
    {
        $client = static::createClient();

        // Créer un groupe
        $response = $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Original',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $id = $data['id'];
        $iri = $data['@id'];

        // Mettre à jour le groupe
        $client->request('PUT', $iri, [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe Modifié',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'id' => $id,
            'nom' => 'Groupe Modifié',
        ]);
    }

    /**
     * Test DELETE d'un groupe
     */
    public function testDeleteRfidGroupe(): void
    {
        $client = static::createClient();

        // Créer un groupe
        $response = $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Groupe à supprimer',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $iri = $data['@id'];

        // Supprimer le groupe
        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertEmpty($client->getResponse()->getContent());

        // Vérifier que le groupe n'existe plus
        $client->request('GET', $iri);
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test filtre par nom (partial match)
     */
    public function testFilterByNomPartial(): void
    {
        $client = static::createClient();

        // Créer plusieurs groupes
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Scolaire Primaire'],
        ]);
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Scolaire Secondaire'],
        ]);
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Groupe Adultes'],
        ]);

        // Filtrer par "Scolaire"
        $response = $client->request('GET', '/api/rfid_groupes?nom=Scolaire');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(2, count($data['member']));

        // Vérifier que tous les résultats contiennent "Scolaire"
        foreach ($data['member'] as $item) {
            $this->assertStringContainsString('Scolaire', $item['nom']);
        }
    }

    /**
     * Test que la collection retourne tous les groupes sans filtre
     */
    public function testGetAllWithoutFilter(): void
    {
        $client = static::createClient();

        // Créer plusieurs groupes
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Groupe 1'],
        ]);
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Groupe 2'],
        ]);
        $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Groupe 3'],
        ]);

        // Récupérer tous les groupes
        $response = $client->request('GET', '/api/rfid_groupes');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(3, count($data['member']));
    }

    /**
     * Test pagination
     */
    public function testPagination(): void
    {
        $client = static::createClient();

        // Créer 35 groupes (> itemsPerPage qui est 30)
        for ($i = 1; $i <= 35; $i++) {
            $client->request('POST', '/api/rfid_groupes', [
                'headers' => ['Content-Type' => 'application/ld+json'],
                'json' => ['nom' => 'Groupe Pagination ' . $i],
            ]);
        }

        // Récupérer la première page
        $response = $client->request('GET', '/api/rfid_groupes');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('totalItems', $data);

        // Vérifier que la pagination fonctionne
        $this->assertGreaterThanOrEqual(35, $data['totalItems']);
        $this->assertLessThanOrEqual(30, count($data['member'])); // itemsPerPage = 30

        // Vérifier qu'il y a des liens de pagination si plus de 30 items
        if ($data['totalItems'] > 30) {
            $this->assertArrayHasKey('view', $data);
        }
    }

    /**
     * Test que les champs cachés ne sont pas exposés en lecture via DTO
     */
    public function testHiddenFieldsNotExposed(): void
    {
        $client = static::createClient();

        // Créer un groupe
        $response = $client->request('POST', '/api/rfid_groupes', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Test Hidden Fields'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $createData = $response->toArray();
        $id = $createData['id'];

        // Récupérer le groupe via GET (qui utilise le DTO)
        $response = $client->request('GET', '/api/rfid_groupes/' . $id);
        $data = $response->toArray();

        // Vérifier que les champs cachés ne sont PAS présents dans le DTO
        $this->assertArrayNotHasKey('createdAt', $data);
        $this->assertArrayNotHasKey('updatedAt', $data);
        $this->assertArrayNotHasKey('isTosync', $data);
        $this->assertArrayNotHasKey('is_tosync', $data);

        // Vérifier que seuls id et nom sont présents (+ metadata JSON-LD)
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('nom', $data);
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('RfidGroupeOutput', $data['@type']);
    }
}
