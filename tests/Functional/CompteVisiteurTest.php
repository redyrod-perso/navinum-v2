<?php

namespace App\Tests\Functional;

use App\Entity\Visiteur;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/** Comptes en ligne des visiteurs (#4) : inscription, connexion, passage invité → compte, sécurité. */
class CompteVisiteurTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private string $email;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->email = 'lea.'.bin2hex(random_bytes(4)).'@exemple.test';
    }

    private function post(string $url, array $data, ?string $token = null): array
    {
        $this->client->jsonRequest('POST', $url, $data, $token ? ['HTTP_AUTHORIZATION' => 'Bearer '.$token] : []);
        $r = $this->client->getResponse();

        return ['status' => $r->getStatusCode(), 'body' => json_decode((string) $r->getContent(), true)];
    }

    private function visiteur(string $id): Visiteur
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->getRepository(Visiteur::class)->find(Uuid::fromString($id));
    }

    public function testInscriptionEtConnexion(): void
    {
        $inscription = $this->post('/api/visiteur/inscription', ['email' => strtoupper($this->email), 'motDePasse' => 'secret-123', 'pseudo' => 'Léa']);
        $this->assertSame(201, $inscription['status']);
        $this->assertSame(['id', 'pseudo', 'anonyme'], array_keys($inscription['body']['visiteur']), 'profil public : jamais l\'e-mail');
        $this->assertFalse($inscription['body']['visiteur']['anonyme']);
        $this->assertStringStartsWith('nva1.', $inscription['body']['appareil']);

        $visiteur = $this->visiteur($inscription['body']['visiteur']['id']);
        $this->assertSame($this->email, $visiteur->getEmail(), 'adresse en minuscules');
        $this->assertNotSame('secret-123', $visiteur->getPasswordSon(), 'mot de passe haché');
        $this->assertFalse($visiteur->getHasNewsletter() ?? false, 'pas de newsletter sans consentement explicite');
        $this->assertNull($visiteur->getEmailVerifieAt());

        $connexion = $this->post('/api/visiteur/connexion', ['email' => ' '.ucfirst($this->email), 'motDePasse' => 'secret-123']);
        $this->assertSame(200, $connexion['status']);
        $this->assertSame($inscription['body']['visiteur']['id'], $connexion['body']['visiteur']['id']);
        $this->client->request('GET', '/api/visiteur/moi', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$connexion['body']['token']]);
        $this->assertResponseIsSuccessful();

        foreach ([['email' => $this->email, 'motDePasse' => 'mauvais-mdp'], ['email' => 'inconnu.'.$this->email, 'motDePasse' => 'secret-123']] as $essai) {
            $refus = $this->post('/api/visiteur/connexion', $essai);
            $this->assertSame([401, 'IDENTIFIANTS_INVALIDES', 'Adresse e-mail ou mot de passe incorrect.'], [$refus['status'], $refus['body']['code'], $refus['body']['error']], 'même réponse : ne révèle pas les comptes existants');
        }
    }

    public function testUnInviteDevientUnCompteEtGardeSonIdentite(): void
    {
        $invite = $this->post('/api/visiteur/invite', ['pseudo' => 'Tom']);
        $id = $invite['body']['visiteur']['id'];

        $compte = $this->post('/api/visiteur/inscription', ['email' => $this->email, 'motDePasse' => 'secret-123', 'newsletter' => true], $invite['body']['token']);
        $this->assertSame(201, $compte['status']);
        $this->assertSame($id, $compte['body']['visiteur']['id'], 'même visiteur : mêmes XP, médailles, historique');
        $this->assertSame('Tom', $compte['body']['visiteur']['pseudo']);
        $this->assertArrayNotHasKey('appareil', $compte['body'], 'même appareil : le client garde son secret');
        $this->assertTrue($this->visiteur($id)->getHasNewsletter());
        $this->assertSame('compte', $this->visiteur($id)->getType());

        // Le secret d'appareil de l'invité reste valable pour reprendre la session
        $this->assertSame(200, $this->post('/api/visiteur/reprendre', ['pseudo' => 'Tom', 'appareil' => $invite['body']['appareil']])['status']);
    }

    public function testRefus(): void
    {
        $this->post('/api/visiteur/inscription', ['email' => $this->email, 'motDePasse' => 'secret-123']);
        $doublon = $this->post('/api/visiteur/inscription', ['email' => strtoupper($this->email), 'motDePasse' => 'autre-secret']);
        $this->assertSame([409, 'EMAIL_DEJA_UTILISE'], [$doublon['status'], $doublon['body']['code']]);

        foreach ([['email' => 'pas-une-adresse', 'motDePasse' => 'secret-123'], ['email' => 'b'.$this->email, 'motDePasse' => 'court'], ['email' => 'c'.$this->email, 'motDePasse' => 'secret-123', 'pseudo' => 'X']] as $data) {
            $res = $this->post('/api/visiteur/inscription', $data);
            $this->assertSame([400, 'VALIDATION'], [$res['status'], $res['body']['code']], json_encode($data));
        }
    }

    public function testTropDeTentativesDeConnexion(): void
    {
        $this->post('/api/visiteur/inscription', ['email' => $this->email, 'motDePasse' => 'secret-123']);
        for ($i = 0; $i < 10; ++$i) {
            $this->assertSame(401, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'mauvais-'.$i])['status']);
        }
        $bloque = $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'secret-123']);
        $this->assertSame([429, 'TROP_DE_TENTATIVES'], [$bloque['status'], $bloque['body']['code']], 'même le bon mot de passe est refusé pendant le blocage');
    }

    /** Authentificateur du pare-feu api : jeton visiteur → ROLE_VISITEUR, tolérant si le jeton est invalide. */
    public function testAuthentificateurDuParefeuApi(): void
    {
        $jeton = $this->post('/api/visiteur/invite', ['pseudo' => 'Zoé'])['body']['token'];

        $this->client->request('GET', '/api/visiteurs', server: ['HTTP_ACCEPT' => 'application/ld+json']);
        $this->assertResponseStatusCodeSame(401);
        $this->client->request('GET', '/api/visiteurs', server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer '.$jeton]);
        $this->assertResponseStatusCodeSame(403, 'visiteur authentifié, mais pas administrateur');

        $this->client->request('GET', '/api/expositions', server: ['HTTP_ACCEPT' => 'application/ld+json', 'HTTP_AUTHORIZATION' => 'Bearer nvv2.faux.jeton']);
        $this->assertResponseIsSuccessful('un jeton invalide ne bloque pas le catalogue public');
    }

    public function testNouveauMotDePasseParLAdmin(): void
    {
        $id = $this->post('/api/visiteur/inscription', ['email' => $this->email, 'motDePasse' => 'oublie-123'])['body']['visiteur']['id'];
        $this->login($this->client);
        $crawler = $this->client->request('GET', "/admin/visiteurs/$id/edit");
        $form = $crawler->filter('form')->form();
        $this->client->submit($form, ['visiteur[nouveauMotDePasse]' => 'nouveau-456']);
        $this->assertResponseRedirects();

        $this->assertSame(401, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'oublie-123'])['status']);
        $this->assertSame(200, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'nouveau-456'])['status']);
    }
}
