<?php

namespace App\Tests\Functional;

use App\Tests\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminRfidTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/rfids');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/rfids/new');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testLoginAndAdminRfidsSuccessful(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/admin/rfids');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessNewRfidForm(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/rfids/new');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="rfid[valeur1]"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testAdminCanCreateNewRfid(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/rfids/new');

        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'visiteur',
            'rfid[valeur1]' => 'TEST-' . uniqid(),
            'rfid[valeur2]' => 'VALUE2',
            'rfid[valeur3]' => 'VALUE3',
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testAdminListShowsCreatedRfids(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueValeur = 'TEST-LIST-' . uniqid();

        $crawler = $client->request('GET', '/admin/rfids/new');
        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'visiteur',
            'rfid[valeur1]' => $uniqueValeur,
            'rfid[valeur2]' => 'VALUE2',
            'rfid[valeur3]' => 'VALUE3',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $this->assertStringContainsString($uniqueValeur, $client->getResponse()->getContent());
    }

    public function testAdminCanAccessEditRfidForm(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Rfid::class);
        $this->login($client);

        $uniqueValeur = 'TEST-EDIT-' . uniqid();
        $crawler = $client->request('GET', '/admin/rfids/new');
        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'visiteur',
            'rfid[valeur1]' => $uniqueValeur,
            'rfid[valeur2]' => 'VALUE2',
            'rfid[valeur3]' => 'VALUE3',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="rfid[valeur1]"]');

        $valeur1Value = $crawler->filter('input[name="rfid[valeur1]"]')->attr('value');
        $this->assertEquals($uniqueValeur, $valeur1Value);
    }

    public function testAdminCanUpdateRfid(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Rfid::class);
        $this->login($client);

        $originalValeur = 'TEST-ORIGINAL-' . uniqid();
        $crawler = $client->request('GET', '/admin/rfids/new');
        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'visiteur',
            'rfid[valeur1]' => $originalValeur,
            'rfid[valeur2]' => 'VALUE2',
            'rfid[valeur3]' => 'VALUE3',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $updatedValeur = 'TEST-UPDATED-' . uniqid();
        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'animateur',
            'rfid[valeur1]' => $updatedValeur,
            'rfid[valeur2]' => 'UPDATED2',
            'rfid[valeur3]' => 'UPDATED3',
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        $this->assertStringContainsString($updatedValeur, $client->getResponse()->getContent());
    }

    public function testAdminListShowsCreateButton(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/rfids');

        $this->assertSelectorExists('a.ui.primary.button');
        $this->assertStringContainsString('Créer', $client->getResponse()->getContent());
    }

    public function testAdminListShowsActionButtons(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueValeur = 'TEST-ACTIONS-' . uniqid();
        $crawler = $client->request('GET', '/admin/rfids/new');
        $form = $crawler->filter('form')->form([
            'rfid[type]' => 'visiteur',
            'rfid[valeur1]' => $uniqueValeur,
            'rfid[valeur2]' => 'VALUE2',
            'rfid[valeur3]' => 'VALUE3',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $this->assertSelectorExists('a[title="Éditer"]');
        $this->assertSelectorExists('button[title="Supprimer"]');
        $this->assertSelectorExists('i.edit.icon');
        $this->assertSelectorExists('i.trash.icon');
    }

    public function testEmptyListShowsAppropriateMessage(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Rfid::class);
        $this->login($client);

        $client->request('GET', '/admin/rfids');

        $content = $client->getResponse()->getContent();
        $hasNoResultsMessage = str_contains($content, 'Aucun résultat') || str_contains($content, 'Aucune donnée');
        $this->assertTrue($hasNoResultsMessage, 'Empty list should show "no results" message');
    }
}
