<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\LogVisite;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** API de lancement : GET /api/launch/{token} et POST /api/launch/{token}/events */
class LaunchApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get(EntityManagerInterface::class);
    }

    private function token(string $ttl = LaunchToken::DEFAULT_TTL): LaunchToken
    {
        $suffix = bin2hex(random_bytes(3));
        $quiz = (new Interactif())->setLibelle('Quiz test '.$suffix)->setVersion('1.0')->setVariable(json_encode([
            'type' => 'object',
            'properties' => [
                'tempsParQuestion' => ['type' => 'integer', 'default' => 20],
                'nbQuestions' => ['type' => 'integer', 'default' => 10],
            ],
        ]));
        $parcours = (new Parcours())->setLibelle('Parcours test '.$suffix);
        $placement = $parcours->addInteractif($quiz, ['nbQuestions' => 5]);
        $token = new LaunchToken($quiz, $placement, $ttl);
        $this->em->persist($quiz);
        $this->em->persist($parcours);
        $this->em->persist($token);
        $this->em->flush();

        return $token;
    }

    public function testContexteAvecConfigFusionnee(): void
    {
        $token = $this->token();
        $this->client->request('GET', '/api/launch/'.$token->getToken());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['tempsParQuestion' => 20, 'nbQuestions' => 5], $data['config']);
        $this->assertStringStartsWith('Quiz test', $data['interactif']['libelle']);
        $this->assertStringStartsWith('Parcours test', $data['parcours']['libelle']);
        $this->assertNull($data['visiteur']);
        $this->assertStringContainsString('/navinum/parcours-interactif/', $data['mercure']['topics']['config']);
    }

    public function testJetonInconnuEtExpire(): void
    {
        $this->client->request('GET', '/api/launch/'.str_repeat('a', 48));
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame('LIEN_INVALIDE', json_decode($this->client->getResponse()->getContent(), true)['code']);

        $expired = $this->token('-1 minute');
        $this->client->request('GET', '/api/launch/'.$expired->getToken());
        $this->assertResponseStatusCodeSame(410);
        $this->assertSame('LIEN_EXPIRE', json_decode($this->client->getResponse()->getContent(), true)['code']);
    }

    public function testEvenementsConsignesDansLogVisite(): void
    {
        $token = $this->token();
        $url = '/api/launch/'.$token->getToken().'/events';

        $this->client->jsonRequest('POST', $url, ['type' => 'quiz.started', 'payload' => ['theme' => 'histoire']]);
        $this->assertResponseStatusCodeSame(202);
        $this->client->jsonRequest('POST', $url, ['type' => 'quiz.completed', 'payload' => ['score' => 7]]);
        $this->assertResponseStatusCodeSame(202);
        $logId = json_decode($this->client->getResponse()->getContent(), true)['logVisite'];

        $this->em->clear();
        $log = $this->em->find(LogVisite::class, $logId);
        $this->assertSame(7, $log->getScore());
        $this->assertNotNull($log->getEndAt());
        $this->assertCount(2, json_decode($log->getResultats(), true));
    }

    public function testEvenementInvalide(): void
    {
        $url = '/api/launch/'.$this->token()->getToken().'/events';
        $this->client->jsonRequest('POST', $url, ['payload' => []]);
        $this->assertResponseStatusCodeSame(400);
        $this->client->jsonRequest('POST', $url, ['type' => 'x', 'payload' => 'pas un objet']);
        $this->assertResponseStatusCodeSame(400);
    }
}
