<?php

namespace App\Tests\Functional;

use App\Tests\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminInteractifTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/interactifs');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/interactifs/new');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testLoginAndAdminInteractifsSuccessful(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/admin/interactifs');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessNewInteractifForm(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/interactifs/new');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="interactif[libelle]"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testAdminCanCreateNewInteractif(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/interactifs/new');

        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => 'Test Interactif ' . uniqid(),
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testAdminListShowsCreatedInteractifs(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueLibelle = 'Test Liste Interactif ' . uniqid();

        $crawler = $client->request('GET', '/admin/interactifs/new');
        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $this->assertStringContainsString($uniqueLibelle, $client->getResponse()->getContent());
    }

    public function testAdminCanAccessEditInteractifForm(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Interactif::class);
        $this->login($client);

        $uniqueLibelle = 'Test Edit Interactif ' . uniqid();
        $crawler = $client->request('GET', '/admin/interactifs/new');
        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="interactif[libelle]"]');

        $libelleValue = $crawler->filter('input[name="interactif[libelle]"]')->attr('value');
        $this->assertEquals($uniqueLibelle, $libelleValue);
    }

    public function testAdminCanUpdateInteractif(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Interactif::class);
        $this->login($client);

        $originalLibelle = 'Test Original Interactif ' . uniqid();
        $crawler = $client->request('GET', '/admin/interactifs/new');
        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => $originalLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $updatedLibelle = 'Test Updated Interactif ' . uniqid();
        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => $updatedLibelle,
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        $this->assertStringContainsString($updatedLibelle, $client->getResponse()->getContent());
    }

    public function testAdminListShowsCreateButton(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/interactifs');

        $this->assertSelectorExists('a.ui.primary.button');
        $this->assertStringContainsString('Créer', $client->getResponse()->getContent());
    }

    public function testAdminListShowsActionButtons(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueLibelle = 'Test Actions Interactif ' . uniqid();
        $crawler = $client->request('GET', '/admin/interactifs/new');
        $form = $crawler->filter('form')->form([
            'interactif[libelle]' => $uniqueLibelle,
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
        $this->clearTable($client, \App\Entity\Interactif::class);
        $this->login($client);

        $client->request('GET', '/admin/interactifs');

        $content = $client->getResponse()->getContent();
        $hasNoResultsMessage = str_contains($content, 'Aucun résultat') || str_contains($content, 'Aucune donnée');
        $this->assertTrue($hasNoResultsMessage, 'Empty list should show "no results" message');
    }
}
