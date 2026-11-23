<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\Medaille;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * #57 : le score de fin de partie multijoueur est repris de la session contrôlée par le serveur
 * (règle `scoreSession`), pas de ce que déclare le navigateur.
 */
class SessionScoreTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $s;
    private string $launch;
    private string $visiteur;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->s = bin2hex(random_bytes(3));
        $em = $this->client->getContainer()->get(EntityManagerInterface::class);
        $em->persist((new Medaille())->setLibelle('Sans faute '.$this->s)->setIsUnique(false));
        $question = ['question' => 'Q ?', 'reponses' => ['a', 'b', 'c', 'd'], 'correcte' => 0];
        $quiz = (new Interactif())->setLibelle('Quiz contrôlé '.$this->s)
            ->setVariable(json_encode(['type' => 'object', 'properties' => [
                'nbQuestions' => ['type' => 'integer', 'default' => 3],
                'themes' => ['type' => 'array', 'default' => [['id' => 'histoire', 'libelle' => 'Histoire', 'questions' => array_fill(0, 5, $question)]]],
            ]]))
            ->setGamification([
                'xp' => [['evenement' => 'quiz.completed', 'points' => 'score * 10']],
                'medailles' => [['medaille' => 'Sans faute '.$this->s, 'evenement' => 'quiz.completed', 'si' => 'total > 0 and score == total']],
                'attribuables' => [],
                'scoreSession' => ['quiz.completed'],
            ]);
        $token = new LaunchToken($quiz);
        $em->persist($quiz);
        $em->persist($token);
        $em->flush();
        $this->launch = $token->getToken();

        $this->client->jsonRequest('POST', '/api/visiteur/invite', ['pseudo' => 'Léa']);
        $this->visiteur = json_decode($this->client->getResponse()->getContent(), true)['token'];
        $this->client->jsonRequest('POST', "/api/launch/{$this->launch}/visiteur", [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->visiteur]);
    }

    private function post(string $url, array $data): array
    {
        $this->client->jsonRequest('POST', $url, $data, ['HTTP_AUTHORIZATION' => 'Bearer '.$this->visiteur]);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /** Partie dans le groupe $groupe, terminée avec $points bonnes réponses enregistrées par le serveur. */
    private function partie(string $groupe, int $points): void
    {
        $this->post('/api/session/create', ['playerName' => 'Léa', 'sessionId' => $groupe]);
        $this->post("/api/session/$groupe/start", ['playerName' => 'Léa', 'theme' => 'histoire']);
        for ($i = 1; $i <= $points; ++$i) {
            $this->post("/api/session/$groupe/score", ['playerName' => 'Léa', 'score' => $i]);
        }
    }

    public function testScoreDeclareRemplaceParCeluiDeLaSession(): void
    {
        $groupe = 'Groupe '.$this->s;
        $this->partie($groupe, 1);

        // Le navigateur annonce un sans-faute ; le serveur n'a enregistré qu'une bonne réponse sur 3
        $gains = $this->post("/api/launch/{$this->launch}/events", ['type' => 'quiz.completed', 'payload' => [
            'score' => 3, 'total' => 3, 'victoire' => true, 'groupe' => $groupe, 'joueur' => 'Léa',
        ]])['gamification'];
        $this->assertSame([['typologie' => 'Général', 'points' => 10]], $gains['xp'], 'score 1 (session), pas 3');
        $this->assertSame([], $gains['medailles'], 'pas de « Sans faute »');
    }

    public function testVraiSansFauteReconnu(): void
    {
        $groupe = 'Parfait '.$this->s;
        $this->partie($groupe, 3);
        $gains = $this->post("/api/launch/{$this->launch}/events", ['type' => 'quiz.completed', 'payload' => [
            'score' => 3, 'total' => 999, 'groupe' => $groupe, 'joueur' => 'Léa',
        ]])['gamification'];
        $this->assertSame(30, $gains['xp'][0]['points']);
        $this->assertSame(['Sans faute '.$this->s], array_column($gains['medailles'], 'libelle'), 'total repris de la configuration (3)');
    }

    public function testSansSessionAucunPoint(): void
    {
        $gains = $this->post("/api/launch/{$this->launch}/events", ['type' => 'quiz.completed', 'payload' => [
            'score' => 3, 'total' => 3, 'groupe' => 'Inexistant '.$this->s, 'joueur' => 'Léa',
        ]])['gamification'];
        $this->assertSame([], $gains['xp']);
        $this->assertSame([], $gains['medailles']);
    }
}
