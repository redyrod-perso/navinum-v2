<?php

namespace App\Tests\Functional;

use App\Controller\SessionController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

/**
 * Les changements de session de quiz sont publiés sur Mercure (topic /navinum/sessions/{id}),
 * plus de flux SSE maintenu par PHP.
 */
class SessionMercureTest extends WebTestCase
{
    public function testChaqueChangementDeSessionEstPublie(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        /** @var list<Update> $published */
        $published = [];
        static::getContainer()->set('mercure.hub.default', new MockHub(
            'http://hub.test/.well-known/mercure',
            new StaticTokenProvider('jwt'),
            function (Update $update) use (&$published): string {
                $published[] = $update;

                return 'id-'.\count($published);
            },
        ));

        $id = 'test-'.bin2hex(random_bytes(4));
        $client->jsonRequest('POST', '/api/session/create', ['playerName' => 'Alice', 'sessionId' => $id]);
        $this->assertResponseIsSuccessful();
        $client->jsonRequest('POST', "/api/session/$id/join", ['playerName' => 'Bob']);
        $this->assertResponseIsSuccessful();
        $client->jsonRequest('POST', "/api/session/$id/start", ['playerName' => 'Alice', 'theme' => 'histoire']);
        $this->assertResponseIsSuccessful();

        $this->assertCount(3, $published);
        foreach ($published as $update) {
            $this->assertSame([SessionController::sessionTopic($id)], $update->getTopics());
        }
        $last = json_decode($published[2]->getData(), true);
        $this->assertSame('session', $last['type']);
        $this->assertSame('playing', $last['session']['status']);
        $this->assertSame('histoire', $last['session']['theme']);
        $this->assertSame(['Alice', 'Bob'], array_column($last['session']['players'], 'name'));
    }

    public function testLeFluxPhpNExistePlus(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/session/abc/stream');
        $this->assertResponseStatusCodeSame(404);
    }
}
