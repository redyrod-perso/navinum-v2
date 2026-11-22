<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cycle de vie des sessions de quiz (une session par groupe).
 *
 * Bug corrigé : après une partie, la session restait à l'état « playing » pour toujours ;
 * rejouer dans le même groupe échouait (« Le quiz a déjà commencé »), même quand tous les
 * joueurs étaient partis.
 */
class SessionLifecycleTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $groupe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->groupe = 'Groupe '.bin2hex(random_bytes(4));
    }

    private function post(string $url, array $data): array
    {
        $this->client->jsonRequest('POST', $url, $data);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'body' => json_decode($this->client->getResponse()->getContent(), true)];
    }

    /** Partie lancée par Alice avec Bob. */
    private function partieEnCours(): void
    {
        $this->post('/api/session/create', ['playerName' => 'Alice', 'sessionId' => $this->groupe]);
        $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Bob']);
        $this->assertSame(200, $this->post("/api/session/{$this->groupe}/start", ['playerName' => 'Alice', 'theme' => 'histoire'])['status']);
    }

    public function testRejouerDansLeMemeGroupeApresUnePartie(): void
    {
        $this->partieEnCours();
        $this->post("/api/session/{$this->groupe}/leave", ['playerName' => 'Alice']);
        $this->post("/api/session/{$this->groupe}/leave", ['playerName' => 'Bob']);

        // Tous partis : la session est supprimée, le groupe est de nouveau libre
        $this->client->request('GET', "/api/session/{$this->groupe}");
        $this->assertResponseStatusCodeSame(404);
        $res = $this->post('/api/session/create', ['playerName' => 'Bob', 'sessionId' => $this->groupe]);
        $this->assertSame(200, $res['status']);
        $this->assertSame('lobby', $res['body']['session']['status']);
        $this->assertSame('Bob', $res['body']['session']['leader']);
    }

    public function testPartieAbandonneeRouverteALArrivee(): void
    {
        $this->partieEnCours();
        $this->vieillirSession(16 * 60); // aucune activité depuis 16 minutes, joueurs jamais partis

        $res = $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Chloé']);
        $this->assertSame(200, $res['status']);
        $this->assertSame('lobby', $res['body']['session']['status']);
        $this->assertSame(['Chloé'], array_column($res['body']['session']['players'], 'name'));
        $this->assertSame('Chloé', $res['body']['session']['leader']);
    }

    public function testPartieEnCoursRefuseeClairement(): void
    {
        $this->partieEnCours();
        $res = $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Chloé']);
        $this->assertSame(409, $res['status']);
        $this->assertStringContainsString('partie est en cours', $res['body']['error']);

        // La création ne doit pas écraser une partie en cours
        $this->assertSame(409, $this->post('/api/session/create', ['playerName' => 'Chloé', 'sessionId' => $this->groupe])['status']);
    }

    public function testLeLeaderQuiPartTransmetSonRole(): void
    {
        $this->post('/api/session/create', ['playerName' => 'Alice', 'sessionId' => $this->groupe]);
        $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Bob']);
        $res = $this->post("/api/session/{$this->groupe}/leave", ['playerName' => 'Alice']);
        $this->assertSame('Bob', $res['body']['session']['leader']);
        $this->assertTrue($res['body']['session']['players'][0]['isLeader']);
    }

    private function vieillirSession(int $secondes): void
    {
        $file = static::getContainer()->getParameter('kernel.project_dir').'/var/cache/sessions/'.$this->groupe.'.json';
        $session = json_decode(file_get_contents($file), true);
        $session['lastUpdate'] -= $secondes;
        file_put_contents($file, json_encode($session));
    }
}
