<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminMenuTest extends WebTestCase
{
    private function login($client): void
    {
        $crawler = $client->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->submit($form);
    }

    public function testAdminMenuContainsAllModules(): void
    {
        $client = static::createClient();
        $this->login($client);

        // Access any admin page to check the menu
        $crawler = $client->request('GET', '/admin/expositions');

        $this->assertResponseIsSuccessful();

        // Check that all menu items are present
        $menuItems = $crawler->filter('.ui.menu a');
        $menuText = $menuItems->text(null, true);

        // Check for each module in the menu
        $this->assertStringContainsString('Expositions', $menuText);
        $this->assertStringContainsString('Interactifs', $menuText);
        $this->assertStringContainsString('Visiteurs', $menuText);
        $this->assertStringContainsString('RFID', $menuText);
        $this->assertStringContainsString('Flottes', $menuText);
        $this->assertStringContainsString('Périphériques', $menuText);
    }

    public function testMenuLinksAreClickable(): void
    {
        $client = static::createClient();
        $this->login($client);

        // Test each menu link
        $routes = [
            '/admin/expositions' => 'Expositions',
            '/admin/interactifs' => 'Interactifs',
            '/admin/visiteurs' => 'Visiteurs',
            '/admin/rfid' => 'RFID',
            '/admin/flottes' => 'Flottes',
            '/admin/peripheriques' => 'Périphériques',
        ];

        foreach ($routes as $url => $label) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful(
                sprintf('Failed to access %s (%s)', $label, $url)
            );
        }
    }
}
