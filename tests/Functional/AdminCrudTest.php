<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours complet de chaque section de l'admin : accès protégé, liste, création, modification, suppression.
 */
class AdminCrudTest extends WebTestCase
{
    use DatabaseTestTrait;

    /** @return iterable<string, array{string, string, string, \Closure(string, KernelBrowser): array<string, string>}> */
    public static function sections(): iterable
    {
        // [url, préfixe du formulaire, champ modifié, données de création]
        yield 'expositions' => ['/admin/expositions', 'exposition', 'libelle', fn (string $v) => ['exposition[libelle]' => $v]];
        yield 'parcours' => ['/admin/parcours', 'app_parcours', 'libelle', fn (string $v) => ['app_parcours[libelle]' => $v]];
        yield 'interactifs' => ['/admin/interactifs', 'interactif', 'libelle', fn (string $v) => ['interactif[libelle]' => $v]];
        yield 'organisateurs' => ['/admin/organisateurs', 'organisateur', 'libelle', fn (string $v) => ['organisateur[libelle]' => $v]];
        yield 'flottes' => ['/admin/flottes', 'flotte', 'libelle', fn (string $v) => ['flotte[libelle]' => $v]];
        yield 'visiteurs' => ['/admin/visiteurs', 'visiteur', 'nom', fn (string $v) => ['visiteur[nom]' => $v]];
        yield 'peripheriques' => ['/admin/peripheriques', 'peripherique', 'adresse_mac', fn (string $v, KernelBrowser $c) => [
            'peripherique[adresse_mac]' => $v,
            'peripherique[interactif]' => (string) self::anyInteractif($c)->getId(),
        ]];
        yield 'médailles' => ['/admin/medailles', 'medaille', 'libelle', fn (string $v) => ['medaille[libelle]' => $v]];
        yield 'typologies' => ['/admin/typologies', 'typologie', 'libelle', fn (string $v) => ['typologie[libelle]' => $v]];
        yield 'comptes' => ['/admin/comptes', 'user', 'nom', fn (string $v) => [
            'user[email]' => strtolower($v).'@test.local',
            'user[nom]' => $v,
            'user[storedRoles]' => ['ROLE_ADMIN'],
            'user[plainPassword]' => 'motdepasse-test',
        ]];
    }

    private static function anyInteractif(KernelBrowser $client): Interactif
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $i = (new Interactif())->setLibelle('Pour périphérique '.bin2hex(random_bytes(3)));
        $em->persist($i);
        $em->flush();

        return $i;
    }

    #[DataProvider('sections')]
    public function testAccesProtege(string $url, mixed ...$ignored): void
    {
        $client = static::createClient();
        $client->request('GET', $url);
        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location'));
    }

    #[DataProvider('sections')]
    public function testCreationModificationSuppression(string $url, string $prefix, string $field, \Closure $data): void
    {
        $client = static::createClient();
        $this->login($client);
        $value = 'Test'.bin2hex(random_bytes(4));

        // Liste et formulaire de création
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $values = $data($value, $client); // peut créer des données liées (avant l'affichage du formulaire)
        $crawler = $client->request('GET', $url.'/new');
        $this->assertResponseIsSuccessful();

        // Création
        $form = $crawler->filter('form[name="'.$prefix.'"]')->form();
        $client->submit($form, $values);
        $this->assertResponseRedirects(message: 'Création refusée : '.$client->getResponse()->getContent());
        $client->followRedirect();
        $client->request('GET', $url.'?q='.urlencode($value));
        $this->assertSelectorTextContains('table', $value);

        // Modification
        $crawler = $client->request('GET', $url.'?q='.urlencode($value));
        $editLink = $crawler->filter('a[title="Modifier"]')->first()->attr('href');
        $crawler = $client->request('GET', $editLink);
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[name="'.$prefix.'"]')->form(), [$prefix.'['.$field.']' => $value.'Bis']);
        $this->assertResponseRedirects();
        $client->request('GET', $url.'?q='.urlencode($value.'Bis'));
        $this->assertSelectorTextContains('table', $value.'Bis');

        // Suppression
        $crawler = $client->request('GET', $url.'?q='.urlencode($value.'Bis'));
        $client->submit($crawler->filter('form[action$="/delete"]')->first()->form());
        $this->assertResponseRedirects($url);
        $crawler = $client->request('GET', $url.'?q='.urlencode($value.'Bis'));
        $this->assertCount(0, $crawler->filter('a[title="Modifier"]'), 'L\'élément doit avoir été supprimé');
    }

    public function testMenuEtTableauDeBord(): void
    {
        $client = static::createClient();
        $this->login($client);
        $crawler = $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        foreach (['/admin/expositions', '/admin/parcours', '/admin/interactifs', '/admin/visiteurs', '/admin/rfid', '/admin/comptes'] as $href) {
            $this->assertCount(1, $crawler->filter('nav a[href="'.$href.'"]'), "Lien de menu manquant : $href");
        }
    }

    public function testMauvaisJetonCsrfALaSuppression(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $this->login($client);
        $i = (new Interactif())->setLibelle('Csrf '.bin2hex(random_bytes(3)));
        $em->persist($i);
        $em->flush();

        $client->request('POST', '/admin/interactifs/'.$i->getId().'/delete', ['_token' => 'faux']);
        $em->clear();
        $this->assertNotNull($em->find(Interactif::class, $i->getId()), "L'interactif ne doit pas être supprimé");
    }
}
