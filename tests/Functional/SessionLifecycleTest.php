<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use Doctrine\ORM\EntityManagerInterface;
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
        $this->assertSame('PARTIE_EN_COURS', $res['body']['code']);

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

    /** Codes métier stables dans les réponses d'erreur (lus par NavinumAPI.erreurs). */
    public function testCodesDErreur(): void
    {
        $this->assertSame('VALIDATION', $this->post('/api/session/create', [])['body']['code']);
        $this->assertSame('SESSION_INTROUVABLE', $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Ana'])['body']['code']);

        $this->post('/api/session/create', ['playerName' => 'Alice', 'sessionId' => $this->groupe]);
        $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Bob']);
        $refus = $this->post("/api/session/{$this->groupe}/start", ['playerName' => 'Bob', 'theme' => 'histoire']);
        $this->assertSame([403, 'NON_LEADER'], [$refus['status'], $refus['body']['code']]);

        $this->post("/api/session/{$this->groupe}/start", ['playerName' => 'Alice', 'theme' => 'histoire']);
        $deja = $this->post("/api/session/{$this->groupe}/start", ['playerName' => 'Alice', 'theme' => 'histoire']);
        $this->assertSame([409, 'PARTIE_DEMARREE'], [$deja['status'], $deja['body']['code']]);
    }

    /** Tout nom de groupe saisi par les joueurs reste une session du dossier des sessions. */
    public function testNomDeGroupeQuelconque(): void
    {
        $dir = static::getContainer()->getParameter('kernel.project_dir');
        $nom = '../../public/'.bin2hex(random_bytes(4));

        $res = $this->post('/api/session/create', ['playerName' => 'Ana', 'sessionId' => $nom]);
        $this->assertSame(200, $res['status']);
        $this->assertSame($nom, $res['body']['session']['id']);
        $this->assertFileDoesNotExist($dir.'/'.substr($nom, 6).'.json');
        $this->assertFileExists($dir.'/var/cache/sessions/'.hash('sha256', $nom).'.json');

        $trop = $this->post('/api/session/create', ['playerName' => 'Ana', 'sessionId' => str_repeat('x', 101)]);
        $this->assertSame([400, 'SESSION_INVALIDE'], [$trop['status'], $trop['body']['code']]);
    }

    /** Les routes de maintenance (suppression de sessions) sont réservées à l'administration. */
    public function testMaintenanceReserveeALAdmin(): void
    {
        $this->post('/api/session/create', ['playerName' => 'Ana', 'sessionId' => $this->groupe]);
        $this->assertSame(401, $this->post('/api/sessions/clear', [])['status']);
        $this->assertSame(401, $this->post("/api/session/{$this->groupe}/reset", [])['status']);

        $this->client->request('GET', "/api/session/{$this->groupe}");
        $this->assertResponseIsSuccessful();
    }

    /** #17 : le nombre maximum de joueurs est imposé par le serveur (configuration du lancement, sinon 8). */
    public function testGroupeCompletRefuseParLeServeur(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = (new Interactif())->setLibelle('Quiz duo '.bin2hex(random_bytes(3)))
            ->setVariable(json_encode(['type' => 'object', 'properties' => ['joueursMax' => ['type' => 'integer', 'default' => 2]]]));
        $token = new LaunchToken($quiz);
        $em->persist($quiz);
        $em->persist($token);
        $em->flush();

        $this->post('/api/session/create', ['playerName' => 'Alice', 'sessionId' => $this->groupe, 'launchToken' => $token->getToken()]);
        $this->assertSame(200, $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Bob'])['status']);
        $refus = $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Chloé']);
        $this->assertSame([409, 'GROUPE_COMPLET'], [$refus['status'], $refus['body']['code']]);
        $this->assertStringContainsString('2 joueurs maximum', $refus['body']['error']);
        $this->assertSame(409, $this->post('/api/session/create', ['playerName' => 'Chloé', 'sessionId' => $this->groupe])['status']);
        // Un joueur déjà présent peut revenir
        $this->assertSame(200, $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'Bob'])['status']);
    }

    public function testSansLancementHuitJoueursMaximum(): void
    {
        $this->post('/api/session/create', ['playerName' => 'J0', 'sessionId' => $this->groupe, 'launchToken' => 'inconnu']);
        for ($i = 1; $i < 8; ++$i) {
            $this->assertSame(200, $this->post("/api/session/{$this->groupe}/join", ['playerName' => "J$i"])['status']);
        }
        $this->assertSame('GROUPE_COMPLET', $this->post("/api/session/{$this->groupe}/join", ['playerName' => 'J8'])['body']['code']);
    }

    /** Un score ne progresse que d'un point à la fois, pendant une partie, pour un joueur du groupe. */
    public function testScoreInvraisemblableRefuse(): void
    {
        $this->post('/api/session/create', ['playerName' => 'Alice', 'sessionId' => $this->groupe]);
        $score = fn (array $data) => $this->post("/api/session/{$this->groupe}/score", $data);

        $this->assertSame('SCORE_REFUSE', $score(['playerName' => 'Alice', 'score' => 1])['body']['code'], 'pas avant le démarrage');
        $this->post("/api/session/{$this->groupe}/start", ['playerName' => 'Alice', 'theme' => 'histoire']);

        $this->assertSame(200, $score(['playerName' => 'Alice', 'score' => 1])['status']);
        $this->assertSame(200, $score(['playerName' => 'Alice', 'score' => 1])['status'], 'même score renvoyé : sans effet');
        $this->assertSame(200, $score(['playerName' => 'Alice', 'score' => 2])['status']);
        foreach ([['playerName' => 'Alice', 'score' => 10], ['playerName' => 'Alice', 'score' => 1],
            ['playerName' => 'Alice', 'score' => '3'], ['playerName' => 'Intrus', 'score' => 1]] as $data) {
            $res = $score($data);
            $this->assertSame([409, 'SCORE_REFUSE'], [$res['status'], $res['body']['code']], json_encode($data));
        }
        $this->client->request('GET', "/api/session/{$this->groupe}");
        $this->assertSame(2, json_decode($this->client->getResponse()->getContent(), true)['session']['players'][0]['score']);
    }

    private function vieillirSession(int $secondes): void
    {
        $file = static::getContainer()->getParameter('kernel.project_dir').'/var/cache/sessions/'.hash('sha256', $this->groupe).'.json';
        $session = json_decode(file_get_contents($file), true);
        $session['lastUpdate'] -= $secondes;
        file_put_contents($file, json_encode($session));
    }
}
