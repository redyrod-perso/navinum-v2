<?php

namespace App\Tests\Functional;

use App\Tests\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminVisiteurTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/visiteurs');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/visiteurs/new');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testLoginAndAdminVisiteursSuccessful(): void
    {
        $client = static::createClient();
        $this->login($client);

        $client->request('GET', '/admin/visiteurs');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessNewVisiteurForm(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/visiteurs/new');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="visiteur[email]"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testAdminCanCreateNewVisiteur(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/visiteurs/new');

        $form = $crawler->filter('form')->form([
            'visiteur[email]' => 'test-' . uniqid() . '@example.com',
            'visiteur[prenom]' => 'Test',
            'visiteur[nom]' => 'Visiteur',
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testAdminListShowsCreatedVisiteurs(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueEmail = 'test-list-' . uniqid() . '@example.com';

        $crawler = $client->request('GET', '/admin/visiteurs/new');
        $form = $crawler->filter('form')->form([
            'visiteur[email]' => $uniqueEmail,
            'visiteur[prenom]' => 'Test',
            'visiteur[nom]' => 'Liste',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $this->assertStringContainsString($uniqueEmail, $client->getResponse()->getContent());
    }

    public function testAdminCanAccessEditVisiteurForm(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Visiteur::class);
        $this->login($client);

        $uniqueEmail = 'test-edit-' . uniqid() . '@example.com';
        $crawler = $client->request('GET', '/admin/visiteurs/new');
        $form = $crawler->filter('form')->form([
            'visiteur[email]' => $uniqueEmail,
            'visiteur[prenom]' => 'Test',
            'visiteur[nom]' => 'Edit',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="visiteur[email]"]');

        $emailValue = $crawler->filter('input[name="visiteur[email]"]')->attr('value');
        $this->assertEquals($uniqueEmail, $emailValue);
    }

    public function testAdminCanUpdateVisiteur(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Visiteur::class);
        $this->login($client);

        $originalEmail = 'test-original-' . uniqid() . '@example.com';
        $crawler = $client->request('GET', '/admin/visiteurs/new');
        $form = $crawler->filter('form')->form([
            'visiteur[email]' => $originalEmail,
            'visiteur[prenom]' => 'Original',
            'visiteur[nom]' => 'Test',
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        $updatedEmail = 'test-updated-' . uniqid() . '@example.com';
        $form = $crawler->filter('form')->form([
            'visiteur[email]' => $updatedEmail,
            'visiteur[prenom]' => 'Updated',
            'visiteur[nom]' => 'Test',
        ]);
        $client->submit($form);

        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        $this->assertStringContainsString($updatedEmail, $client->getResponse()->getContent());
    }

    public function testAdminListShowsCreateButton(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', '/admin/visiteurs');

        $this->assertSelectorExists('a.ui.primary.button');
        $this->assertStringContainsString('Créer', $client->getResponse()->getContent());
    }

    public function testAdminListShowsActionButtons(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueEmail = 'test-actions-' . uniqid() . '@example.com';
        $crawler = $client->request('GET', '/admin/visiteurs/new');
        $form = $crawler->filter('form')->form([
            'visiteur[email]' => $uniqueEmail,
            'visiteur[prenom]' => 'Test',
            'visiteur[nom]' => 'Actions',
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
        $this->clearTable($client, \App\Entity\Visiteur::class);
        $this->login($client);

        $client->request('GET', '/admin/visiteurs');

        $content = $client->getResponse()->getContent();
        $hasNoResultsMessage = str_contains($content, 'Aucun résultat') || str_contains($content, 'Aucune donnée');
        $this->assertTrue($hasNoResultsMessage, 'Empty list should show "no results" message');
    }
}
