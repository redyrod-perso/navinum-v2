<?php

namespace App\Tests\Functional;

use App\Entity\Visiteur;
use App\Entity\VisiteurIdentite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Uid\Uuid;

/** Connexion par fournisseur OAuth (#4), fournisseur simulé : aucun appel réseau réel. */
class OAuthTest extends WebTestCase
{
    private KernelBrowser $client;
    /** @var array<string, mixed> profil renvoyé par le faux fournisseur */
    private array $profil;
    private array $appels = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->profil = ['sub' => 'g-'.bin2hex(random_bytes(4)), 'email' => 'ana.'.bin2hex(random_bytes(3)).'@exemple.test', 'email_verified' => true, 'name' => 'Ana Lyse'];
        static::getContainer()->set('http_client', new MockHttpClient(function (string $method, string $url, array $options) {
            $this->appels[] = "$method $url";
            if (str_contains($url, 'token')) {
                return new MockResponse(json_encode(['access_token' => 'jeton-fournisseur']), ['response_headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse(json_encode($this->profil), ['response_headers' => ['content-type' => 'application/json']]);
        }));
    }

    private function json(string $method, string $url, array $data = [], ?string $token = null): array
    {
        $this->client->jsonRequest($method, $url, $data, $token ? ['HTTP_AUTHORIZATION' => 'Bearer '.$token] : []);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'body' => json_decode((string) $this->client->getResponse()->getContent(), true)];
    }

    /** Parcours complet : démarrer → (fournisseur) → retour → échange. Rend la session obtenue. */
    private function connexion(string $nom = 'google', ?string $invite = null): array
    {
        $depart = $this->json('POST', "/api/visiteur/oauth/$nom/demarrer", ['retour' => '/quiz/index.html?nv=abc'], $invite);
        $this->assertSame(200, $depart['status']);
        parse_str((string) parse_url($depart['body']['url'], \PHP_URL_QUERY), $query);

        $this->client->request('GET', "/api/visiteur/oauth/$nom/retour", ['code' => 'code-du-fournisseur', 'state' => $query['state']]);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('http://localhost/quiz/index.html?nv=abc#navinum-oauth=', $location, 'retour sur la page d\'origine, lancement conservé');

        $code = substr($location, strpos($location, '=', strpos($location, '#')) + 1);
        $echange = $this->json('POST', '/api/visiteur/oauth/echange', ['code' => $code]);
        $this->assertSame(200, $echange['status']);
        $this->assertSame(401, $this->json('POST', '/api/visiteur/oauth/echange', ['code' => $code])['status'], 'code à usage unique');

        return $echange['body'];
    }

    public function testFournisseursActifs(): void
    {
        $this->assertSame(['google', 'microsoft'], array_column($this->json('GET', '/api/visiteur/oauth/fournisseurs')['body']['fournisseurs'], 'nom'), 'Facebook non configuré : absent');
        $this->assertSame('MODE_INDISPONIBLE', $this->json('POST', '/api/visiteur/oauth/facebook/demarrer', ['retour' => '/'])['body']['code']);
    }

    public function testConnexionCompleteEtCodeAUsageUnique(): void
    {
        $session = $this->connexion();
        $this->assertSame('Ana Lyse', $session['visiteur']['pseudo']);
        $this->assertSame(['id', 'pseudo', 'anonyme'], array_keys($session['visiteur']), 'jamais d\'e-mail dans le profil');
        $this->assertStringStartsWith('nva1.', $session['appareil']);
        $this->client->request('GET', '/api/visiteur/moi', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$session['token']]);
        $this->assertResponseIsSuccessful();
        $this->assertContains('POST https://oauth2.googleapis.com/token', $this->appels);

        // Même compte Google plus tard : même visiteur
        $this->assertSame($session['visiteur']['id'], $this->connexion()['visiteur']['id']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $visiteur = $em->getRepository(Visiteur::class)->find(Uuid::fromString($session['visiteur']['id']));
        $this->assertSame([$this->profil['email'], 'compte'], [$visiteur->getEmail(), $visiteur->getType()]);
        $this->assertNotNull($visiteur->getEmailVerifieAt(), 'adresse garantie par Google');
        $this->assertFalse($visiteur->getHasNewsletter(), 'pas de newsletter sans consentement');
    }

    public function testUnInviteDevientLeCompte(): void
    {
        $invite = $this->json('POST', '/api/visiteur/invite', ['pseudo' => 'Tom'])['body'];
        $session = $this->connexion('google', $invite['token']);
        $this->assertSame($invite['visiteur']['id'], $session['visiteur']['id'], 'mêmes XP et médailles');
        $this->assertSame('Tom', $session['visiteur']['pseudo']);
    }

    public function testPlusieursFournisseursPourUnMemeVisiteur(): void
    {
        $compte = $this->json('POST', '/api/visiteur/inscription', ['email' => $this->profil['email'], 'motDePasse' => 'secret-123'])['body'];

        // Google garantit l'adresse : liaison au compte existant
        $this->assertSame($compte['visiteur']['id'], $this->connexion('google')['visiteur']['id']);

        // Microsoft ne garantit pas l'adresse : jamais de liaison par e-mail…
        $microsoft = ['sub' => 'ms-'.bin2hex(random_bytes(4)), 'email' => $this->profil['email'], 'name' => 'Ana'];
        $this->profil = $microsoft;
        $this->assertNotSame($compte['visiteur']['id'], $this->connexion('microsoft')['visiteur']['id'], 'adresse non garantie : autre visiteur');

        // … mais un compte connecté qui ajoute Microsoft le lie à lui-même (multi-fournisseurs)
        $this->profil = ['sub' => 'ms-'.bin2hex(random_bytes(4))] + $microsoft;
        $this->assertSame($compte['visiteur']['id'], $this->connexion('microsoft', $compte['token'])['visiteur']['id']);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $liens = $em->getRepository(VisiteurIdentite::class)->findBy(['visiteur' => Uuid::fromString($compte['visiteur']['id'])]);
        $this->assertEqualsCanonicalizing(['google', 'microsoft'], array_map(fn ($l) => $l->getFournisseur(), $liens), 'deux fournisseurs pour un même compte');
    }

    public function testRefus(): void
    {
        $this->assertSame(400, $this->json('POST', '/api/visiteur/oauth/google/demarrer', ['retour' => 'https://site-pirate.example/vol'])['status'], 'retour vers un autre site refusé');
        $this->assertSame(400, $this->json('POST', '/api/visiteur/oauth/google/demarrer', ['retour' => '//site-pirate.example/vol'])['status']);

        $this->client->request('GET', '/api/visiteur/oauth/google/retour', ['code' => 'x', 'state' => 'faux.etat']);
        $this->assertResponseStatusCodeSame(400);

        $depart = $this->json('POST', '/api/visiteur/oauth/google/demarrer', ['retour' => '/jeu/'])['body'];
        parse_str((string) parse_url($depart['url'], \PHP_URL_QUERY), $query);
        $this->client->request('GET', '/api/visiteur/oauth/google/retour', ['error' => 'access_denied', 'state' => $query['state']]);
        $this->assertStringEndsWith('/jeu/#navinum-oauth-erreur=CONNEXION_ANNULEE', (string) $this->client->getResponse()->headers->get('Location'));

        $echange = $this->json('POST', '/api/visiteur/oauth/echange', ['code' => str_repeat('a', 48)]);
        $this->assertSame([401, 'NON_IDENTIFIE'], [$echange['status'], $echange['body']['code']]);
    }
}
