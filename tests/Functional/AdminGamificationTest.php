<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Tests\Traits\DatabaseTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Page « Règles de gamification » d'un interactif : formulaire généré, validation serveur. */
class AdminGamificationTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testEnregistrementEtValidationDesRegles(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $this->login($client);
        $quiz = (new Interactif())->setLibelle('Quiz règles '.bin2hex(random_bytes(3)));
        $em->persist($quiz);
        $em->flush();
        $url = '/admin/interactifs/'.$quiz->getId().'/gamification';

        $crawler = $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#regles-editor');

        // Expression invalide : refusée, rien n'est enregistré
        $client->submit($crawler->filter('#regles-form')->form(['regles' => '{"xp": [{"evenement": "quiz.completed", "points": "score * (10"}]}']));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-danger', 'xp[0].points : expression invalide');

        // Règles valides (points numériques normalisés en texte)
        $client->submit($crawler->filter('#regles-form')->form(['regles' => '{"xp": [{"evenement": "quiz.completed", "points": 50}], "medailles": []}']));
        $this->assertResponseRedirects('/admin/interactifs');
        $em->clear();
        $this->assertSame(
            ['xp' => [['evenement' => 'quiz.completed', 'points' => '50']], 'medailles' => []],
            $em->find(Interactif::class, $quiz->getId())->getGamification(),
        );

        // Règles vidées : suppression (null)
        $client->submit($crawler->filter('#regles-form')->form(['regles' => '{"xp": [], "medailles": []}']));
        $em->clear();
        $this->assertNull($em->find(Interactif::class, $quiz->getId())->getGamification());
    }

    public function testReserveeAuxAdministrateurs(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $quiz = (new Interactif())->setLibelle('Quiz droits '.bin2hex(random_bytes(3)));
        $em->persist($quiz);
        $em->flush();
        $this->login($client, \App\Entity\User::ROLE_ORGANISATEUR);
        $client->request('GET', '/admin/interactifs/'.$quiz->getId().'/gamification');
        $this->assertResponseStatusCodeSame(403);
    }
}
