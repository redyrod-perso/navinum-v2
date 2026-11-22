<?php

namespace App\Tests\Functional;

use App\Entity\Exposition;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sécurité de l'API Platform : catalogue lisible publiquement, données sensibles et écritures réservées à l'admin.
 */
class ApiSecurityTest extends WebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function ressourcesPrivees(): iterable
    {
        yield 'visiteurs (données personnelles)' => ['/api/visiteurs'];
        yield 'badges RFID' => ['/api/rfids'];
        yield 'flottes' => ['/api/flottes'];
        yield 'périphériques' => ['/api/peripheriques'];
    }

    #[DataProvider('ressourcesPrivees')]
    public function testLectureRefuseeSansAuthentification(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(401);
    }

    /** @return iterable<string, array{string}> */
    public static function catalogue(): iterable
    {
        yield 'expositions' => ['/api/expositions'];
        yield 'interactifs' => ['/api/interactifs'];
        yield 'organisateurs' => ['/api/organisateurs'];
        yield 'groupes RFID (quiz)' => ['/api/rfid_groupes'];
    }

    #[DataProvider('catalogue')]
    public function testCatalogueLisibleEtEcritureRefusee(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseIsSuccessful();

        if ('/api/rfid_groupes' !== $url) { // la création de groupe reste publique (quiz)
            $client->request('POST', $url, server: ['CONTENT_TYPE' => 'application/ld+json'], content: '{"libelle": "Pirate"}');
            $this->assertResponseStatusCodeSame(401);
        }
    }

    /**
     * Bug corrigé : GET /api/expositions plantait (référence circulaire Exposition → Parcours → Exposition)
     * dès qu'une exposition avait un parcours.
     */
    public function testExpositionAvecParcoursSerialisable(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $expo = (new Exposition())->setLibelle('Expo circulaire '.bin2hex(random_bytes(3)));
        $parcours = (new Parcours())->setLibelle('Parcours circulaire '.bin2hex(random_bytes(3)))->addExposition($expo);
        $em->persist($expo);
        $em->persist($parcours);
        $em->flush();

        $client->request('GET', '/api/expositions/'.$expo->getId(), server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseIsSuccessful();
        $this->assertArrayNotHasKey('parcours', json_decode($client->getResponse()->getContent(), true));
        $client->request('GET', '/api/expositions', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseIsSuccessful();
    }
}
