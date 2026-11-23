<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\LogVisite;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Visiteur invité : création, jeton, rattachement à un lancement, LogVisite au nom du visiteur. */
class VisiteurInviteTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @return array{0: string, 1: string} [id, jeton] */
    private function invite(string $pseudo): array
    {
        $this->client->jsonRequest('POST', '/api/visiteur/invite', ['pseudo' => $pseudo]);
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame($pseudo, $data['visiteur']['pseudo']);
        $this->assertTrue($data['visiteur']['anonyme']);
        $this->assertArrayNotHasKey('email', $data['visiteur']);

        return [$data['visiteur']['id'], $data['token']];
    }

    private function launchToken(): string
    {
        $em = $this->client->getContainer()->get(EntityManagerInterface::class);
        $s = bin2hex(random_bytes(3));
        $quiz = (new Interactif())->setLibelle('Quiz visiteur '.$s);
        $parcours = (new Parcours())->setLibelle('Parcours visiteur '.$s);
        $token = new LaunchToken($quiz, $parcours->addInteractif($quiz));
        foreach ([$quiz, $parcours, $token] as $e) {
            $em->persist($e);
        }
        $em->flush();

        return $token->getToken();
    }

    public function testInviteEtProfil(): void
    {
        [$id, $token] = $this->invite('Alice');
        $this->assertMatchesRegularExpression('/^nvv2\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);

        $this->client->request('GET', '/api/visiteur/moi', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        $this->assertResponseIsSuccessful();
        $this->assertSame($id, json_decode($this->client->getResponse()->getContent(), true)['visiteur']['id']);

        // Jeton falsifié
        $this->client->request('GET', '/api/visiteur/moi', server: ['HTTP_AUTHORIZATION' => 'Bearer '.substr($token, 0, -2).'xx']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testPseudoInvalide(): void
    {
        $this->client->jsonRequest('POST', '/api/visiteur/invite', ['pseudo' => 'A']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testLesEvenementsSontAuNomDuVisiteur(): void
    {
        [$id, $visiteurToken] = $this->invite('Bob');
        $launch = $this->launchToken();
        $auth = ['HTTP_AUTHORIZATION' => 'Bearer '.$visiteurToken];

        // Un premier événement avant identification, puis rattachement : le LogVisite existant est complété
        $this->client->jsonRequest('POST', "/api/launch/$launch/events", ['type' => 'quiz.started', 'payload' => []]);
        $this->client->jsonRequest('POST', "/api/launch/$launch/visiteur", [], $auth);
        $this->assertResponseIsSuccessful();
        $this->assertSame('Bob', json_decode($this->client->getResponse()->getContent(), true)['visiteur']['pseudo']);

        $this->client->jsonRequest('POST', "/api/launch/$launch/events", ['type' => 'quiz.completed', 'payload' => ['score' => 3]]);
        $logId = json_decode($this->client->getResponse()->getContent(), true)['logVisite'];
        $em = $this->client->getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $this->assertSame($id, (string) $em->find(LogVisite::class, $logId)->getVisiteur()->getId());

        // Un autre visiteur ne peut pas s'approprier ce lancement
        [, $autre] = $this->invite('Charlie');
        $this->client->jsonRequest('POST', "/api/launch/$launch/visiteur", [], ['HTTP_AUTHORIZATION' => 'Bearer '.$autre]);
        $this->assertResponseStatusCodeSame(409);
    }
}
