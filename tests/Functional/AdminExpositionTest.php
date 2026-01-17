<?php

namespace App\Tests\Functional;

use App\Tests\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminExpositionTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testListRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/expositions');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testNewRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/expositions/new');

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/login', $location);
    }

    public function testLoginAndAdminExpositionsSuccessfull(): void
    {
        $client = static::createClient();

        // Load login page
        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        // Submit login form (in-memory user: admin / admin)
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);


        $client->request('GET', '/admin/expositions');
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        // We do not follow the redirect here to avoid depending on DB/grid wiring.
    }

    public function testAdminCanAccessNewExpositionForm(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        // Access new exposition form
        $crawler = $client->request('GET', '/admin/expositions/new');
        $this->assertResponseIsSuccessful();

        // Check form elements exist
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="exposition[libelle]"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testAdminCanCreateNewExposition(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        // Access new exposition form
        $crawler = $client->request('GET', '/admin/expositions/new');

        // Fill and submit form
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => 'Test Exposition ' . uniqid(),
        ]);
        $client->submit($form);


        // Should redirect after successful creation
        $this->assertTrue($client->getResponse()->isRedirect());

        // Follow redirect
        $client->followRedirect();

        // Should be back on the list page
        $this->assertResponseIsSuccessful();
    }

    public function testAdminCannotCreateExpositionWithDuplicateLibelle(): void
    {
        $client = static::createClient();
        $this->login($client);

        $uniqueLibelle = 'Test Unique ' . uniqid();

        // Create first exposition
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $client->followRedirect();

        // Try to create second exposition with same libelle
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);

        // For now, this will cause a database error (500)
        // TODO: Add proper validation in the form to catch this before database
        // When proper validation is added, change this to:
        // $this->assertResponseIsSuccessful();
        // $this->assertSelectorExists('form');
        // $this->assertSelectorExists('.form-error'); // or whatever error class is used

        // Currently expecting 500 due to database constraint
        $this->assertEquals(500, $client->getResponse()->getStatusCode());
    }

    public function testAdminListShowsCreatedExpositions(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        $uniqueLibelle = 'Test Liste ' . uniqid();

        // Create exposition
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        // Check that the created exposition appears in the list
        $this->assertStringContainsString($uniqueLibelle, $client->getResponse()->getContent());
    }

    public function testAdminCanAccessEditExpositionForm(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Exposition::class);

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        // Create an exposition first
        $uniqueLibelle = 'Test Edit ' . uniqid();
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        // Find edit link in the list
        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        // Should show edit form
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="exposition[libelle]"]');

        // Form should be pre-filled with existing value
        $libelleValue = $crawler->filter('input[name="exposition[libelle]"]')->attr('value');
        $this->assertEquals($uniqueLibelle, $libelleValue);
    }

    public function testAdminCanUpdateExposition(): void
    {
        $client = static::createClient();
        $this->login($client);

        // Create an exposition
        $originalLibelle = 'Test Original ' . uniqid();
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $originalLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        // Click edit link (find the one for our specific exposition)
        $editLink = $crawler->filter('a[title="Éditer"]')->first()->link();
        $crawler = $client->click($editLink);

        // Update the exposition
        $updatedLibelle = 'Test Updated ' . uniqid();
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $updatedLibelle,
        ]);
        $client->submit($form);

        // Should redirect after update
        $this->assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();

        // Check updated value appears in list
        $this->assertStringContainsString($updatedLibelle, $client->getResponse()->getContent());
    }

    public function testAdminListShowsCreateButton(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        // Access list
        $crawler = $client->request('GET', '/admin/expositions');

        // Check create button exists
        $this->assertSelectorExists('a.ui.primary.button');
        $this->assertStringContainsString('Créer', $client->getResponse()->getContent());
    }

    public function testAdminListShowsActionButtons(): void
    {
        $client = static::createClient();

        // Login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);

        // Create an exposition
        $uniqueLibelle = 'Test Actions ' . uniqid();
        $crawler = $client->request('GET', '/admin/expositions/new');
        $form = $crawler->filter('form')->form([
            'exposition[libelle]' => $uniqueLibelle,
        ]);
        $client->submit($form);
        $crawler = $client->followRedirect();

        // Check action buttons exist in the table
        $this->assertSelectorExists('a[title="Éditer"]');
        $this->assertSelectorExists('button[title="Supprimer"]');
        $this->assertSelectorExists('i.edit.icon');
        $this->assertSelectorExists('i.trash.icon');
    }

    public function testEmptyListShowsAppropriateMessage(): void
    {
        $client = static::createClient();
        $this->clearTable($client, \App\Entity\Exposition::class);
        $this->login($client);

        // After clearing database, list should be empty
        $client->request('GET', '/admin/expositions');

        // Should show "no results" message
        $content = $client->getResponse()->getContent();
        $hasNoResultsMessage = str_contains($content, 'Aucun résultat') || str_contains($content, 'Aucune donnée');
        $this->assertTrue($hasNoResultsMessage, 'Empty list should show "no results" message');
    }
}
