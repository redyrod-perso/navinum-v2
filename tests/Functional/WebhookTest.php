<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\Medaille;
use App\Entity\Webhook;
use App\Entity\WebhookEnvoi;
use App\Message\EnvoyerWebhook;
use App\Tests\Traits\DatabaseTestTrait;
use App\Webhook\EnvoyerWebhookHandler;
use App\Webhook\WebhookEchecSubscriber;
use App\Webhook\Webhooks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

/** Webhooks sortants signés (#10). */
class WebhookTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM '.Webhook::class)->execute(); // isoler les tests (journal supprimé en cascade)
        $this->transport()->reset();
    }

    private function transport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    private function webhook(array $evenements, bool $actif = true): Webhook
    {
        $webhook = (new Webhook('n8n test', 'http://n8n.test/webhook/x', $evenements, 'whsec_test'))->setActif($actif);
        $this->em->persist($webhook);
        $this->em->flush();

        return $webhook;
    }

    /** Événements publiés (envois en file), par type. */
    private function enFile(): array
    {
        return array_map(function (Envelope $e) {
            return $this->em->getRepository(WebhookEnvoi::class)->find(Uuid::fromString($e->getMessage()->envoiId))->getEvenement();
        }, array_values(array_filter($this->transport()->getSent(), fn (Envelope $e) => $e->getMessage() instanceof EnvoyerWebhook)));
    }

    public function testSeulsLesWebhooksAbonnesEtActifsRecoiventLEvenement(): void
    {
        $this->webhook(['medaille.obtenue']);
        $this->webhook(['visiteur.inscrit']);
        $this->webhook(['medaille.obtenue'], false);
        static::getContainer()->get(Webhooks::class)->publier('medaille.obtenue', ['x' => 1]);
        $this->assertSame(['medaille.obtenue'], $this->enFile());
    }

    public function testEnvoiSigneEtJournalise(): void
    {
        $this->webhook(['visiteur.inscrit']);
        $this->client->jsonRequest('POST', '/api/visiteur/inscription', ['email' => 'wh.'.bin2hex(random_bytes(3)).'@exemple.test', 'motDePasse' => 'secret-123', 'newsletter' => true]);
        $this->assertSame(['visiteur.inscrit'], $this->enFile(), 'inscription → événement');

        $recu = [];
        $handler = new EnvoyerWebhookHandler($this->em, new MockHttpClient(function ($method, $url, $options) use (&$recu) {
            $recu = ['url' => $url, 'headers' => $options['normalized_headers'], 'body' => $options['body']];

            return new MockResponse('ok', ['http_code' => 200]);
        }), static::getContainer()->get('clock'));
        $message = $this->transport()->getSent()[0]->getMessage();
        $handler($message);

        $this->assertSame('http://n8n.test/webhook/x', $recu['url']);
        $horodatage = (int) substr($recu['headers']['x-navinum-horodatage'][0], \strlen('X-Navinum-Horodatage: '));
        $signature = substr($recu['headers']['x-navinum-signature'][0], \strlen('X-Navinum-Signature: '));
        $this->assertSame(Webhooks::signer('whsec_test', $horodatage, $recu['body']), $signature, 'signature HMAC vérifiable par le destinataire');
        $corps = json_decode($recu['body'], true);
        $this->assertSame('visiteur.inscrit', $corps['evenement']);
        $this->assertTrue($corps['donnees']['visiteur']['newsletter']);
        $this->assertSame('compte', $corps['donnees']['mode']);

        $envoi = $this->em->getRepository(WebhookEnvoi::class)->find(Uuid::fromString($message->envoiId));
        $this->em->refresh($envoi);
        $this->assertSame([WebhookEnvoi::SUCCES, 1, 200], [$envoi->getStatut(), $envoi->getTentatives(), $envoi->getCodeHttp()]);
    }

    public function testEchecApresLesReessais(): void
    {
        $webhook = $this->webhook(['medaille.obtenue']);
        static::getContainer()->get(Webhooks::class)->publier('medaille.obtenue', ['x' => 1]);
        $envelope = $this->transport()->getSent()[0];
        $handler = new EnvoyerWebhookHandler($this->em, new MockHttpClient(new MockResponse('panne', ['http_code' => 500])), static::getContainer()->get('clock'));

        try {
            $handler($envelope->getMessage());
            $this->fail('une réponse 500 doit lever une exception (réessai Messenger)');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('500', $e->getMessage());
        }
        $envoi = $this->em->getRepository(WebhookEnvoi::class)->find(Uuid::fromString($envelope->getMessage()->envoiId));
        $this->assertSame([WebhookEnvoi::EN_ATTENTE, 1, 500], [$envoi->getStatut(), $envoi->getTentatives(), $envoi->getCodeHttp()], 'réessai prévu');

        $echec = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('500'));
        (new WebhookEchecSubscriber($this->em))($echec); // dernier essai : plus de réessai
        $this->assertSame(WebhookEnvoi::ECHEC, $envoi->getStatut());
        $this->assertSame($webhook, $envoi->getWebhook());
    }

    public function testMedailleObtenueEtPartieTerminee(): void
    {
        $this->webhook(['medaille.obtenue', 'partie.terminee']);
        $s = bin2hex(random_bytes(3));
        $this->em->persist((new Medaille())->setLibelle('Fin '.$s)->setIsUnique(true));
        $quiz = (new Interactif())->setLibelle('Quiz webhook '.$s)->setGamification(['xp' => [], 'medailles' => [['medaille' => 'Fin '.$s, 'evenement' => 'jeu.completed']], 'attribuables' => []]);
        $launch = new LaunchToken($quiz);
        $this->em->persist($quiz);
        $this->em->persist($launch);
        $this->em->flush();
        $this->client->jsonRequest('POST', '/api/visiteur/invite', ['pseudo' => 'Léa']);
        $jeton = json_decode($this->client->getResponse()->getContent(), true)['token'];
        $this->client->jsonRequest('POST', '/api/launch/'.$launch->getToken().'/visiteur', [], ['HTTP_AUTHORIZATION' => 'Bearer '.$jeton]);

        $this->client->jsonRequest('POST', '/api/launch/'.$launch->getToken().'/events', ['type' => 'jeu.completed', 'payload' => ['score' => 3]], ['HTTP_AUTHORIZATION' => 'Bearer '.$jeton]);
        $this->assertResponseStatusCodeSame(202);
        $this->assertSame(['medaille.obtenue', 'partie.terminee'], $this->enFile());
        $this->client->jsonRequest('POST', '/api/launch/'.$launch->getToken().'/events', ['type' => 'jeu.started', 'payload' => []], ['HTTP_AUTHORIZATION' => 'Bearer '.$jeton]);
        $this->assertSame([], $this->enFile(), 'un événement qui n’est pas une fin de partie : rien (file vidée entre deux requêtes)');
    }

    public function testAdministration(): void
    {
        $this->login($this->client);
        $crawler = $this->client->request('GET', '/admin/integrations');
        $form = $crawler->selectButton('Créer le webhook')->form(['libelle' => 'n8n', 'url' => 'ftp://mauvais']);
        $form['evenements'][0]->tick();
        $this->client->submit($form);
        $this->assertSame(0, $this->em->getRepository(Webhook::class)->count([]), 'adresse non http(s) refusée');

        $form['url'] = 'http://n8n:5678/webhook/medailles';
        $this->client->submit($form);
        $webhook = $this->em->getRepository(Webhook::class)->findOneBy([]);
        $this->assertSame(['medaille.obtenue'], $webhook->getEvenements());
        $this->assertStringStartsWith('whsec_', $webhook->getSecret());

        $crawler = $this->client->request('GET', '/admin/integrations');
        $this->client->submit($crawler->selectButton('Tester')->form());
        $this->assertSame(['test.ping'], $this->enFile());
    }
}
