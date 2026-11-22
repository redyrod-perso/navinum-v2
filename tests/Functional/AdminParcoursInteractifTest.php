<?php

namespace App\Tests\Functional;

use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\Organisateur;
use App\Entity\Parcours;
use App\Entity\ParcoursInteractif;
use App\Entity\User;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Parcours : placement d'un interactif, configuration via le JSON Schema, lancement, droits organisateur. */
class AdminParcoursInteractifTest extends WebTestCase
{
    use DatabaseTestTrait;

    private function seed(EntityManagerInterface $em, ?Organisateur $org = null): array
    {
        $s = bin2hex(random_bytes(3));
        $quiz = (new Interactif())->setLibelle('Quiz '.$s)->setUrlFichierInteractif('/quiz/index.html')->setVariable(json_encode([
            'type' => 'object',
            'properties' => ['tempsParQuestion' => ['type' => 'integer', 'minimum' => 5, 'default' => 20]],
        ]));
        $expo = (new Exposition())->setLibelle('Expo '.$s)->setOrganisateurEditeur($org);
        $parcours = (new Parcours())->setLibelle('Parcours '.$s)->addExposition($expo);
        foreach ([$quiz, $expo, $parcours] as $e) {
            $em->persist($e);
        }
        $em->flush();

        return [$quiz, $parcours];
    }

    public function testAjoutConfigurationEtLancement(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $this->login($client);
        [$quiz, $parcours] = $this->seed($em);
        $base = '/admin/parcours/'.$parcours->getId();

        $crawler = $client->request('GET', $base);
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->filter('form[action$="/interactifs"]')->form(['interactif' => (string) $quiz->getId()]));
        $this->assertResponseRedirects($base);

        $em->clear();
        $placement = $em->getRepository(ParcoursInteractif::class)->findOneBy(['interactif' => $quiz->getId()]);
        $this->assertNotNull($placement);
        $configUrl = $base.'/interactifs/'.$placement->getId().'/config';

        // Le formulaire est généré depuis le schéma
        $crawler = $client->request('GET', $configUrl);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#config-editor');

        // Valeur hors bornes : refusée par la validation serveur
        $client->submit($crawler->filter('#config-form')->form(['config' => '{"tempsParQuestion": 1}']));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-danger', 'tempsParQuestion : doit être ≥ 5');

        $client->submit($crawler->filter('#config-form')->form(['config' => '{"tempsParQuestion": 45}']));
        $this->assertResponseRedirects($base);
        $em->clear();
        $this->assertSame(['tempsParQuestion' => 45], $em->find(ParcoursInteractif::class, $placement->getId())->getConfig());

        // Lancement : redirection vers l'interactif avec son jeton
        $crawler = $client->request('GET', $base);
        $client->submit($crawler->filter('form[action$="/lancer"]')->form());
        $location = $client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('#^/quiz/index\.html\?nv=[a-f0-9]{48}$#', $location);

        $client->request('GET', '/api/launch/'.substr($location, strpos($location, '=') + 1));
        $this->assertSame(45, json_decode($client->getResponse()->getContent(), true)['config']['tempsParQuestion']);
    }

    public function testOrganisateurLimiteASesParcours(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $mine = (new Organisateur())->setLibelle('Org A '.bin2hex(random_bytes(3)));
        $other = (new Organisateur())->setLibelle('Org B '.bin2hex(random_bytes(3)));
        $em->persist($mine);
        $em->persist($other);
        $em->flush();
        [, $myParcours] = $this->seed($em, $mine);
        [, $otherParcours] = $this->seed($em, $other);

        $this->login($client, User::ROLE_ORGANISATEUR, $mine);

        $client->request('GET', '/admin/parcours/'.$myParcours->getId());
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/admin/parcours/'.$otherParcours->getId());
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/admin/parcours');
        $this->assertSelectorTextContains('table', $myParcours->getLibelle());
        $this->assertSelectorTextNotContains('table', $otherParcours->getLibelle());

        // Le catalogue et les comptes sont réservés à ROLE_ADMIN
        $client->request('GET', '/admin/interactifs');
        $this->assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin/comptes');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testImportDuManifesteDuQuiz(): void
    {
        $client = static::createClient();
        $this->login($client);
        $crawler = $client->request('GET', '/admin/interactifs/import');
        $manifest = json_decode(file_get_contents(dirname(__DIR__, 2).'/public/quiz/navinum.json'), true);
        $manifest['libelle'] .= ' '.bin2hex(random_bytes(3));
        $client->submit($crawler->filter('form')->form(['manifest' => json_encode($manifest)]));
        $this->assertResponseRedirects();

        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $quiz = $em->getRepository(Interactif::class)->findOneBy(['libelle' => $manifest['libelle']]);
        $this->assertTrue($quiz->isConfigurable());
        $this->assertSame(20, $quiz->getDefaultConfig()['tempsParQuestion']);
        $this->assertNotEmpty($quiz->getDefaultConfig()['themes']);

        // Gamification déclarée dans le manifeste : règles par défaut et médailles créées
        $this->assertSame('quiz.completed', $quiz->getGamification()['xp'][0]['evenement']);
        $this->assertSame([], \App\Gamification\RulesSchema::validate($quiz->getGamification()));
        foreach ($manifest['gamification']['medailles'] as $definition) {
            $this->assertNotNull($em->getRepository(\App\Entity\Medaille::class)->findOneBy(['libelle' => $definition['libelle']]));
        }
    }
}
