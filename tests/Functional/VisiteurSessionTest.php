<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\LogVisite;
use App\Entity\Parcours;
use App\Entity\Visiteur;
use App\Entity\VisiteurAppareil;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Sessions visiteur (#6) : 30 min d'inactivité, 12 h au plus, reprise sur le même appareil, révocation.
 */
class VisiteurSessionTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = static::mockTime('2026-11-23 10:00:00');
        $this->client = static::createClient();
    }

    private function call(string $method, string $url, array $data = [], ?string $token = null): array
    {
        $server = null !== $token ? ['HTTP_AUTHORIZATION' => 'Bearer '.$token] : [];
        $this->client->jsonRequest($method, $url, $data, $server);
        $response = $this->client->getResponse();

        return ['status' => $response->getStatusCode(), 'body' => json_decode((string) $response->getContent(), true)];
    }

    private function invite(string $pseudo = 'Léa'): array
    {
        $res = $this->call('POST', '/api/visiteur/invite', ['pseudo' => $pseudo]);
        $this->assertSame(201, $res['status']);
        $this->assertStringStartsWith('nva1.', $res['body']['appareil']);
        $this->assertSame('2026-11-23T10:30:00+00:00', $res['body']['expiresAt']);

        return $res['body'];
    }

    public function testInactiviteDe30Minutes(): void
    {
        $invite = $this->invite();
        $this->clock->sleep(29 * 60);
        $this->assertSame(200, $this->call('GET', '/api/visiteur/moi', token: $invite['token'])['status']);

        $this->clock->sleep(2 * 60); // 31 min sans renouvellement
        $expire = $this->call('GET', '/api/visiteur/moi', token: $invite['token']);
        $this->assertSame([401, 'NON_IDENTIFIE'], [$expire['status'], $expire['body']['code']]);
        $this->assertSame(401, $this->call('POST', '/api/visiteur/renouveler', token: $invite['token'])['status']);
    }

    public function testActiviteProlongeLaSessionJusquA12Heures(): void
    {
        $token = $this->invite()['token'];
        for ($i = 0; $i < 23; ++$i) { // renouvellement toutes les 25 min pendant 9 h 35
            $this->clock->sleep(25 * 60);
            $res = $this->call('POST', '/api/visiteur/renouveler', token: $token);
            $this->assertSame(200, $res['status']);
            $token = $res['body']['token'];
        }
        for ($i = 0; $i < 5; ++$i) {
            $this->clock->sleep(25 * 60);
            $res = $this->call('POST', '/api/visiteur/renouveler', token: $token);
            $this->assertSame(200, $res['status']);
            $token = $res['body']['token'];
        }
        // 11 h 40 : le dernier jeton est plafonné à 12 h après l'ouverture de la session
        $this->assertSame('2026-11-23T22:00:00+00:00', $res['body']['expiresAt']);
        $this->clock->sleep(20 * 60);
        $this->assertSame(401, $this->call('POST', '/api/visiteur/renouveler', token: $token)['status']);
    }

    public function testRepriseSurLeMemeAppareilConserveLeProfil(): void
    {
        $invite = $this->invite('Tom');
        $this->clock->sleep(3 * 3600); // revient 3 h plus tard

        $reprise = $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Tom', 'appareil' => $invite['appareil']]);
        $this->assertSame(200, $reprise['status']);
        $this->assertSame($invite['visiteur']['id'], $reprise['body']['visiteur']['id'], 'même profil (XP, médailles)');
        $this->assertSame(200, $this->call('GET', '/api/visiteur/moi', token: $reprise['body']['token'])['status']);

        foreach ([['pseudo' => 'Autre', 'appareil' => $invite['appareil']], ['pseudo' => 'Tom', 'appareil' => 'nva1.'.str_repeat('0', 64)], ['pseudo' => 'Tom']] as $data) {
            $refus = $this->call('POST', '/api/visiteur/reprendre', $data);
            $this->assertSame([401, 'NON_IDENTIFIE'], [$refus['status'], $refus['body']['code']], json_encode($data));
        }
    }

    public function testDeconnexionDeLAppareil(): void
    {
        $invite = $this->invite();
        $this->client->jsonRequest('POST', '/api/visiteur/deconnexion', [], ['HTTP_AUTHORIZATION' => 'Bearer '.$invite['token']]);
        $this->assertResponseStatusCodeSame(204);
        $this->assertSame(401, $this->call('GET', '/api/visiteur/moi', token: $invite['token'])['status'], 'jeton invalidé immédiatement');
        $this->assertSame(401, $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Léa', 'appareil' => $invite['appareil']])['status']);
    }

    public function testDeconnecterPartoutDepuisLAdmin(): void
    {
        $telephone = $this->invite('Zoé');
        $tablette = $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Zoé', 'appareil' => $telephone['appareil']])['body'];

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->login($this->client);
        $crawler = $this->client->request('GET', '/admin/visiteurs?q=Zoé');
        $form = $crawler->filter(\sprintf('form[action$="/%s/deconnecter"]', $telephone['visiteur']['id']))->form();
        $this->client->submit($form);
        $this->assertResponseRedirects('/admin/visiteurs');

        foreach ([$telephone['token'], $tablette['token']] as $token) {
            $this->assertSame(401, $this->call('GET', '/api/visiteur/moi', token: $token)['status']);
        }
        $actifs = $em->getRepository(VisiteurAppareil::class)->count(['visiteur' => Uuid::fromString($telephone['visiteur']['id']), 'revokedAt' => null]);
        $this->assertSame(0, $actifs);
    }

    /** Les visiteurs du prototype (jeton nvv1) gardent leur profil : l'ancien jeton s'échange une fois. */
    public function testAncienJetonEchange(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $visiteur = (new Visiteur())->setPseudoSon('Ancien')->setIsAnonyme(true)->setType('invite');
        $em->persist($visiteur);
        $em->flush();
        $id = $visiteur->getId()->toRfc4122();
        $secret = static::getContainer()->getParameter('kernel.secret');
        $ancien = 'nvv1.'.$id.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', 'visiteur:'.$id, $secret, true)), '+/', '-_'), '=');

        $this->assertSame(401, $this->call('GET', '/api/visiteur/moi', token: $ancien)['status'], 'plus accepté comme jeton d\'accès');
        $res = $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Ancien', 'ancienJeton' => $ancien]);
        $this->assertSame(200, $res['status']);
        $this->assertSame($id, $res['body']['visiteur']['id']);
        $this->assertStringStartsWith('nva1.', $res['body']['appareil']);
        $this->assertSame(401, $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Autre', 'ancienJeton' => $ancien])['status']);
    }

    /** Borne partagée : le joueur suivant reprend le lancement dès que le précédent n'est plus actif. */
    public function testBornePartageeJoueursSuccessifs(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = (new Interactif())->setLibelle('Quiz borne '.bin2hex(random_bytes(3)));
        $launch = new LaunchToken($quiz);
        $em->persist($quiz);
        $em->persist($launch);
        $em->flush();
        $l = $launch->getToken();

        $alice = $this->invite('Alice');
        $this->assertSame(200, $this->call('POST', "/api/launch/$l/visiteur", token: $alice['token'])['status']);
        $this->call('POST', "/api/launch/$l/events", ['type' => 'quiz.completed', 'payload' => ['score' => 3]]);

        $bob = $this->invite('Bob');
        $refus = $this->call('POST', "/api/launch/$l/visiteur", token: $bob['token']);
        $this->assertSame([409, 'DEJA_RATTACHE'], [$refus['status'], $refus['body']['code']], 'Alice encore active');

        // Fin de partie d'Alice sur la borne : déconnexion → Bob prend la suite
        $this->client->jsonRequest('POST', '/api/visiteur/deconnexion', [], ['HTTP_AUTHORIZATION' => 'Bearer '.$alice['token']]);
        $this->assertSame(200, $this->call('POST', "/api/launch/$l/visiteur", token: $bob['token'])['status']);
        $this->call('POST', "/api/launch/$l/events", ['type' => 'quiz.completed', 'payload' => ['score' => 5]]);

        $logs = $em->getRepository(LogVisite::class)->findBy(['connection' => 'launch:'.substr($l, 0, 16)], ['score' => 'ASC']);
        $this->assertCount(2, $logs, 'une visite par joueur');
        $this->assertSame([$alice['visiteur']['id'], 3], [(string) $logs[0]->getVisiteur()->getId(), $logs[0]->getScore()]);
        $this->assertSame([$bob['visiteur']['id'], 5], [(string) $logs[1]->getVisiteur()->getId(), $logs[1]->getScore()]);

        // Sans déconnexion, 30 min d'inactivité suffisent
        $chloe = $this->invite('Chloé');
        $this->clock->sleep(31 * 60);
        $chloe = $this->call('POST', '/api/visiteur/reprendre', ['pseudo' => 'Chloé', 'appareil' => $chloe['appareil']])['body'];
        $this->assertSame(200, $this->call('POST', "/api/launch/$l/visiteur", token: $chloe['token'])['status']);
    }

    /**
     * Mode borne : un joueur parti sans se déconnecter (page fermée en pleine partie) ne bloque pas la borne
     * et ne reçoit pas les gains du joueur suivant.
     */
    public function testModeBorneLeJoueurSuivantReprendToujours(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $s = bin2hex(random_bytes(3));
        $quiz = (new Interactif())->setLibelle('Quiz borne '.$s);
        $parcours = (new Parcours())->setLibelle('Parcours borne '.$s);
        $launch = new LaunchToken($quiz, $parcours->addInteractif($quiz, ['modeBorne' => true]));
        foreach ([$quiz, $parcours, $launch] as $e) {
            $em->persist($e);
        }
        $em->flush();
        $l = $launch->getToken();

        $parti = $this->invite('Parti');
        $this->assertSame(200, $this->call('POST', "/api/launch/$l/visiteur", token: $parti['token'])['status']);
        $suivant = $this->invite('Suivant'); // « Parti » est toujours actif, jamais déconnecté
        $this->assertSame(200, $this->call('POST', "/api/launch/$l/visiteur", token: $suivant['token'])['status']);
        $this->assertSame($suivant['visiteur']['id'], $this->call('GET', "/api/launch/$l")['body']['visiteur']['id']);
    }

    /**
     * Bug : un lien de lancement réutilisé par un autre joueur, alors que le précédent est encore actif, refuse
     * le rattachement (409) ; les événements de la partie créditaient pourtant le joueur précédent.
     */
    public function testLesGainsNeVontJamaisAUnAutreJoueur(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $quiz = (new Interactif())->setLibelle('Quiz partagé '.bin2hex(random_bytes(3)))
            ->setGamification(['xp' => [['evenement' => 'quiz.completed', 'points' => '10']], 'medailles' => [], 'attribuables' => []]);
        $launch = new LaunchToken($quiz);
        $em->persist($quiz);
        $em->persist($launch);
        $em->flush();
        $l = $launch->getToken();

        $alice = $this->invite('Alice');
        $this->call('POST', "/api/launch/$l/visiteur", token: $alice['token']);
        $bob = $this->invite('Bob');
        $this->assertSame(409, $this->call('POST', "/api/launch/$l/visiteur", token: $bob['token'])['status'], 'Alice encore active');

        $partieDeBob = $this->call('POST', "/api/launch/$l/events", ['type' => 'quiz.completed', 'payload' => []], $bob['token']);
        $this->assertSame(202, $partieDeBob['status'], 'l\'événement est accepté');
        $this->assertFalse($partieDeBob['body']['rattache']);
        $this->assertNull($partieDeBob['body']['gamification'], 'ni Bob ni Alice crédités');
        $medaille = $this->call('POST', "/api/launch/$l/medailles", ['medaille' => 'X'], $bob['token']);
        $this->assertSame([409, 'DEJA_RATTACHE'], [$medaille['status'], $medaille['body']['code']]);

        $partieDAlice = $this->call('POST', "/api/launch/$l/events", ['type' => 'quiz.completed', 'payload' => []], $alice['token']);
        $this->assertTrue($partieDAlice['body']['rattache']);
        $this->assertSame(10, $partieDAlice['body']['gamification']['totalXp'], 'Alice : seulement sa propre partie');

        // #56 : la partie de Bob n'est pas dans le journal de visite d'Alice, mais dans le sien
        $journaux = $em->getRepository(LogVisite::class)->findBy(['interactif' => $quiz]);
        $parVisiteur = [];
        foreach ($journaux as $journal) {
            $parVisiteur[(string) $journal->getVisiteur()?->getId()] = \count(json_decode($journal->getResultats(), true));
        }
        ksort($parVisiteur);
        $attendu = [$alice['visiteur']['id'] => 1, $bob['visiteur']['id'] => 1];
        ksort($attendu);
        $this->assertSame($attendu, $parVisiteur, 'un journal par joueur, un événement chacun');

        // Sans jeton visiteur : lancement préparé par Navinum pour ce visiteur, crédité comme avant
        $this->assertSame(20, $this->call('POST', "/api/launch/$l/events", ['type' => 'quiz.completed', 'payload' => []])['body']['gamification']['totalXp']);
    }
}
