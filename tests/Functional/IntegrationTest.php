<?php

namespace App\Tests\Functional;

use App\Entity\CleIntegration;
use App\Entity\Medaille;
use App\Entity\VisiteurMedaille;
use App\Service\ClesIntegration;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Clés d'intégration et API des outils externes (n8n…, #11). */
class IntegrationTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function cle(array $droits): string
    {
        return static::getContainer()->get(ClesIntegration::class)->creer('n8n test', $droits)[1];
    }

    private function appel(string $method, string $url, ?string $secret, array $data = []): array
    {
        $this->client->jsonRequest($method, $url, $data, null !== $secret ? ['HTTP_AUTHORIZATION' => 'Bearer '.$secret] : []);
        $r = $this->client->getResponse();

        return ['status' => $r->getStatusCode(), 'body' => json_decode((string) $r->getContent(), true)];
    }

    public function testClesRefusees(): void
    {
        $this->assertSame(401, $this->appel('GET', '/api/integration/moi', null)['status'], 'sans clé');
        $inconnue = $this->appel('GET', '/api/integration/moi', 'nvk1.'.str_repeat('0', 64));
        $this->assertSame([401, 'NON_IDENTIFIE'], [$inconnue['status'], $inconnue['body']['code']]);

        $secret = $this->cle([CleIntegration::DROIT_STATISTIQUES]);
        $this->assertSame(200, $this->appel('GET', '/api/integration/moi', $secret)['status']);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $cle = $em->getRepository(CleIntegration::class)->findOneBy(['secretHash' => hash('sha256', $secret)]);
        $this->assertNotNull($cle->getLastUsedAt(), 'dernière utilisation mémorisée');
        static::getContainer()->get(ClesIntegration::class)->revoquer($cle);
        $this->assertSame(401, $this->appel('GET', '/api/integration/moi', $secret)['status'], 'clé révoquée');

        $visiteur = $this->appel('POST', '/api/visiteur/invite', null, ['pseudo' => 'Léa'])['body']['token'];
        $this->assertSame(403, $this->appel('GET', '/api/integration/moi', $visiteur)['status'], 'un jeton visiteur n’ouvre pas l’API des intégrations');
    }

    public function testDroitsParCle(): void
    {
        $stats = $this->cle([CleIntegration::DROIT_STATISTIQUES]);
        $moi = $this->appel('GET', '/api/integration/moi', $stats);
        $this->assertSame(['n8n test', ['statistiques']], [$moi['body']['libelle'], $moi['body']['droits']]);
        $chiffres = $this->appel('GET', '/api/integration/statistiques', $stats);
        $this->assertSame(200, $chiffres['status']);
        $this->assertSame(['visiteurs', 'comptes', 'visites', 'medaillesObtenues', 'xpTotal'], array_keys($chiffres['body']));
        $this->assertSame(403, $this->appel('POST', '/api/integration/medailles', $stats, ['visiteur' => 'x', 'medaille' => 'y'])['status'], 'droit « medailles » manquant');
    }

    public function testAttribuerUneMedaille(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $libelle = 'Visiteur assidu '.bin2hex(random_bytes(3));
        $em->persist((new Medaille())->setLibelle($libelle)->setIsUnique(true));
        $em->flush();
        $visiteur = $this->appel('POST', '/api/visiteur/invite', null, ['pseudo' => 'Tom'])['body']['visiteur']['id'];
        $secret = $this->cle([CleIntegration::DROIT_MEDAILLES]);

        $premiere = $this->appel('POST', '/api/integration/medailles', $secret, ['visiteur' => $visiteur, 'medaille' => $libelle]);
        $this->assertSame([200, true], [$premiere['status'], $premiere['body']['nouvelle']]);
        $this->assertFalse($this->appel('POST', '/api/integration/medailles', $secret, ['visiteur' => $visiteur, 'medaille' => $libelle])['body']['nouvelle'], 'unique : pas de doublon');
        $lien = $em->getRepository(VisiteurMedaille::class)->findOneBy(['medaille' => $em->getRepository(Medaille::class)->findOneBy(['libelle' => $libelle])]);
        $this->assertStringStartsWith('integration:', $lien->getConnection(), 'origine tracée');

        $this->assertSame('MEDAILLE_INCONNUE', $this->appel('POST', '/api/integration/medailles', $secret, ['visiteur' => $visiteur, 'medaille' => 'Inexistante'])['body']['code']);
        $this->assertSame('VISITEUR_INCONNU', $this->appel('POST', '/api/integration/medailles', $secret, ['visiteur' => '01900000-0000-7000-8000-000000000000', 'medaille' => $libelle])['body']['code']);
        $this->assertSame('VALIDATION', $this->appel('POST', '/api/integration/medailles', $secret, ['medaille' => $libelle])['body']['code']);
    }

    public function testAdministrationDesCles(): void
    {
        $this->login($this->client);
        $crawler = $this->client->request('GET', '/admin/integrations');
        $form = $crawler->selectButton('Créer la clé')->form();
        $form['libelle'] = 'n8n prod';
        $form['droits'][0]->tick();
        $crawler = $this->client->submit($form);
        $secret = $crawler->filter('input[readonly]')->attr('value');
        $this->assertStringStartsWith('nvk1.', $secret, 'secret affiché une fois');
        $this->assertSame(200, $this->appel('GET', '/api/integration/moi', $secret)['status']);

        $crawler = $this->client->request('GET', '/admin/integrations');
        $this->assertSame(0, $crawler->filter('input[readonly]')->count(), 'plus jamais réaffiché');
        $this->assertStringNotContainsString($secret, (string) $this->client->getResponse()->getContent());
        $ligne = $crawler->filter('tr')->reduce(fn ($tr) => str_contains($tr->text(), substr($secret, 0, 13)));
        $this->client->submit($ligne->selectButton('Révoquer')->form());
        $this->assertSame(401, $this->appel('GET', '/api/integration/moi', $secret)['status']);
    }
}
