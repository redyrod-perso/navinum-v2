<?php

namespace App\Tests\Functional;

use App\Entity\Visiteur;
use App\Service\LienCompte;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

/** E-mails des comptes visiteurs (#71) : vérification de l'adresse, mot de passe oublié. */
class EmailsCompteTest extends WebTestCase
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

    private function inscrire(): array
    {
        $r = $this->post('/api/visiteur/inscription', ['email' => $this->email, 'motDePasse' => 'secret-123', 'pseudo' => 'Léa']);
        $this->assertSame(201, $r['status']);

        return $r['body'];
    }

    /** Lien (chemin) contenu dans le dernier e-mail mis en file pour l'envoi, et vérifie son destinataire. */
    private function lienDuDernierEmail(string $prefixe): string
    {
        $this->assertQueuedEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertEmailAddressContains($email, 'to', $this->email);
        $this->assertMatchesRegularExpression('#https?://[^/]+('.preg_quote($prefixe, '#').'[A-Za-z0-9_.-]+)#', (string) $email->getTextBody());
        preg_match('#https?://[^/]+('.preg_quote($prefixe, '#').'[A-Za-z0-9_.-]+)#', (string) $email->getTextBody(), $m);
        $this->assertStringContainsString($m[1], (string) $email->getHtmlBody());

        return $m[1];
    }

    public function testVerificationDeLAdresseALInscription(): void
    {
        $compte = $this->inscrire();
        $lien = $this->lienDuDernierEmail('/compte/email/');
        $this->assertNull($this->visiteur($compte['visiteur']['id'])->getEmailVerifieAt());

        $this->client->request('GET', $lien);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Adresse confirmée');
        $this->assertNotNull($this->visiteur($compte['visiteur']['id'])->getEmailVerifieAt());

        // Lien réutilisé : sans effet (déjà vérifiée) ; l'API rend la même chose
        $jeton = substr($lien, \strlen('/compte/email/'));
        $this->assertSame(200, $this->post('/api/visiteur/email/verifier', ['jeton' => $jeton])['status']);

        // Compte vérifié : plus d'e-mail à la connexion
        $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'secret-123']);
        $this->assertQueuedEmailCount(0);
    }

    public function testLiensRefuses(): void
    {
        $compte = $this->inscrire();
        $jeton = substr($this->lienDuDernierEmail('/compte/email/'), \strlen('/compte/email/'));
        $visiteur = $this->visiteur($compte['visiteur']['id']);
        $liens = static::getContainer()->get(LienCompte::class);

        $altere = substr($jeton, 0, -2).('AA' === substr($jeton, -2) ? 'BB' : 'AA');
        $expire = $liens->creer(LienCompte::VERIFICATION, $visiteur, time() - 49 * 3600);
        $autreType = $liens->creer(LienCompte::MOT_DE_PASSE, $visiteur);
        foreach ([$altere, $expire, $autreType, 'nimporte.quoi'] as $mauvais) {
            $r = $this->post('/api/visiteur/email/verifier', ['jeton' => $mauvais]);
            $this->assertSame([400, 'LIEN_COMPTE_INVALIDE'], [$r['status'], $r['body']['code']]);
        }
        $this->client->request('GET', '/compte/email/'.$expire);
        $this->assertResponseStatusCodeSame(400);
        $this->assertNull($this->visiteur($compte['visiteur']['id'])->getEmailVerifieAt());
    }

    public function testConnexionDUnCompteNonVerifieEtRenvoi(): void
    {
        $compte = $this->inscrire();
        $connexion = $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'secret-123']);
        $this->lienDuDernierEmail('/compte/email/');

        $renvoi = $this->post('/api/visiteur/email/renvoyer', [], $connexion['body']['token']);
        $this->assertSame([202, false, true], [$renvoi['status'], $renvoi['body']['emailVerifie'], $renvoi['body']['envoye']]);
        $this->assertQueuedEmailCount(1);

        // Limite : 3 e-mails de vérification par 24 h et par compte (inscription comprise : celle-ci est la 4e)
        $renvoi = $this->post('/api/visiteur/email/renvoyer', [], $connexion['body']['token']);
        $this->assertFalse($renvoi['body']['envoye']);
        $this->assertQueuedEmailCount(0);

        $invite = $this->post('/api/visiteur/invite', ['pseudo' => 'Tom']);
        $this->assertSame(401, $this->post('/api/visiteur/email/renvoyer', [], $invite['body']['token'])['status'], 'réservé aux comptes');
        $this->assertNotNull($compte['visiteur']['id']);
    }

    public function testMotDePasseOublie(): void
    {
        $compte = $this->inscrire();
        $connexion = $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'secret-123']);

        $existant = $this->post('/api/visiteur/mot-de-passe/oublie', ['email' => strtoupper($this->email)]);
        $lien = $this->lienDuDernierEmail('/compte/mot-de-passe/');
        $inconnu = $this->post('/api/visiteur/mot-de-passe/oublie', ['email' => 'inconnu.'.$this->email]);
        $this->assertQueuedEmailCount(0);
        $this->assertSame([202, $existant['body']], [$inconnu['status'], $inconnu['body']], 'même réponse : ne révèle pas les comptes existants');

        // Page du lien : formulaire, puis refus si les deux saisies diffèrent ou si le mot de passe est trop court
        $this->client->request('GET', $lien);
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', $lien, ['motDePasse' => 'nouveau-456', 'confirmation' => 'autre-456']);
        $this->assertResponseStatusCodeSame(422);
        $this->client->request('POST', $lien, ['motDePasse' => 'court', 'confirmation' => 'court']);
        $this->assertResponseStatusCodeSame(422);

        $this->client->request('POST', $lien, ['motDePasse' => 'nouveau-456', 'confirmation' => 'nouveau-456']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mot de passe changé');

        // Sessions révoquées, ancien mot de passe refusé, nouveau accepté, adresse tenue pour vérifiée
        $this->client->request('GET', '/api/visiteur/moi', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$connexion['body']['token']]);
        $this->assertResponseStatusCodeSame(401);
        $this->assertSame(401, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'secret-123'])['status']);
        $this->assertSame(200, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'nouveau-456'])['status']);
        $this->assertNotNull($this->visiteur($compte['visiteur']['id'])->getEmailVerifieAt());

        // Lien à usage unique
        $this->client->request('GET', $lien);
        $this->assertResponseStatusCodeSame(400);
        $r = $this->post('/api/visiteur/mot-de-passe/reinitialiser', ['jeton' => substr($lien, \strlen('/compte/mot-de-passe/')), 'motDePasse' => 'encore-789']);
        $this->assertSame([400, 'LIEN_COMPTE_INVALIDE'], [$r['status'], $r['body']['code']]);
    }

    public function testReinitialisationParLApiEtLienExpire(): void
    {
        $compte = $this->inscrire();
        $visiteur = $this->visiteur($compte['visiteur']['id']);
        $liens = static::getContainer()->get(LienCompte::class);

        $expire = $liens->creer(LienCompte::MOT_DE_PASSE, $visiteur, time() - 3601);
        $r = $this->post('/api/visiteur/mot-de-passe/reinitialiser', ['jeton' => $expire, 'motDePasse' => 'nouveau-456']);
        $this->assertSame([400, 'LIEN_COMPTE_INVALIDE'], [$r['status'], $r['body']['code']]);

        $r = $this->post('/api/visiteur/mot-de-passe/reinitialiser', ['jeton' => $liens->creer(LienCompte::MOT_DE_PASSE, $visiteur), 'motDePasse' => 'court']);
        $this->assertSame([400, 'VALIDATION'], [$r['status'], $r['body']['code']]);

        $r = $this->post('/api/visiteur/mot-de-passe/reinitialiser', ['jeton' => $liens->creer(LienCompte::MOT_DE_PASSE, $visiteur), 'motDePasse' => 'nouveau-456']);
        $this->assertSame(200, $r['status']);
        $this->assertSame(200, $this->post('/api/visiteur/connexion', ['email' => $this->email, 'motDePasse' => 'nouveau-456'])['status']);
    }

    public function testLimiteDesDemandesParAdresse(): void
    {
        $this->inscrire();
        for ($i = 1; $i <= 4; ++$i) {
            $r = $this->post('/api/visiteur/mot-de-passe/oublie', ['email' => $this->email]);
            $this->assertSame(202, $r['status'], 'même réponse, même au-delà de la limite');
            $this->assertQueuedEmailCount($i <= 3 ? 1 : 0, null, 'au plus 3 e-mails par heure et par adresse');
        }
        $this->assertSame(400, $this->post('/api/visiteur/mot-de-passe/oublie', ['email' => 'pas-une-adresse'])['status']);
    }
}
