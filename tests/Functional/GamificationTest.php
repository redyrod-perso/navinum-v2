<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Entity\LaunchToken;
use App\Entity\Medaille;
use App\Entity\Parcours;
use App\Entity\VisiteurMedaille;
use App\Entity\Xp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Gamification de bout en bout : événement → XP et médailles selon les règles de l'interactif. */
class GamificationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private string $suffix;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = $this->client->getContainer()->get(EntityManagerInterface::class);
        $this->suffix = bin2hex(random_bytes(3));
    }

    /** @return array{0: string, 1: string} [jeton de lancement, jeton visiteur] */
    private function partie(bool $identifie = true): array
    {
        $sansFaute = 'Sans faute '.$this->suffix;
        $this->em->persist((new Medaille())->setLibelle($sansFaute)->setIsUnique(true));
        $quiz = (new Interactif())->setLibelle('Quiz gamifié '.$this->suffix)->setGamification([
            'xp' => [
                ['evenement' => 'quiz.completed', 'points' => 'score * 10', 'typologie' => 'Joueur '.$this->suffix],
                ['evenement' => 'quiz.completed', 'points' => '5'],
            ],
            'medailles' => [
                ['medaille' => $sansFaute, 'evenement' => 'quiz.completed', 'si' => 'score == total'],
                ['medaille' => 'Médaille inexistante', 'evenement' => 'quiz.completed'],
            ],
            'attribuables' => [$sansFaute, 'Explorateur '.$this->suffix, 'Médaille inexistante'],
        ]);
        $this->em->persist((new Medaille())->setLibelle('Explorateur '.$this->suffix)->setIsUnique(false));
        $this->em->persist((new Medaille())->setLibelle('Réservée '.$this->suffix)->setIsUnique(true));
        $parcours = (new Parcours())->setLibelle('Parcours gamifié '.$this->suffix);
        $token = new LaunchToken($quiz, $parcours->addInteractif($quiz));
        foreach ([$quiz, $parcours, $token] as $e) {
            $this->em->persist($e);
        }
        $this->em->flush();

        $visiteurToken = '';
        if ($identifie) {
            $this->client->jsonRequest('POST', '/api/visiteur/invite', ['pseudo' => 'Joueur '.$this->suffix]);
            $visiteurToken = json_decode($this->client->getResponse()->getContent(), true)['token'];
            $this->client->jsonRequest('POST', '/api/launch/'.$token->getToken().'/visiteur', [], ['HTTP_AUTHORIZATION' => 'Bearer '.$visiteurToken]);
            $this->assertResponseIsSuccessful();
        }

        return [$token->getToken(), $visiteurToken];
    }

    private function evenement(string $launch, array $payload): array
    {
        $this->client->jsonRequest('POST', "/api/launch/$launch/events", ['type' => 'quiz.completed', 'payload' => $payload]);
        $this->assertResponseStatusCodeSame(202);

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    public function testPartieParfaiteRapporteXpEtMedaille(): void
    {
        [$launch] = $this->partie();
        $result = $this->evenement($launch, ['score' => 3, 'total' => 3])['gamification'];

        $this->assertSame([
            ['typologie' => 'Joueur '.$this->suffix, 'points' => 30],
            ['typologie' => 'Général', 'points' => 5],
        ], $result['xp']);
        $this->assertSame(['Sans faute '.$this->suffix], array_column($result['medailles'], 'libelle'));
        $this->assertSame(35, $result['totalXp']);

        // La médaille inexistante est ignorée sans erreur ; XP enregistrés en base
        $this->assertCount(2, $this->em->getRepository(Xp::class)->findBy(['score' => [30, 5], 'visiteur' => $this->visiteurDe($launch)]));
    }

    public function testMedailleUniqueAttribueeUneSeuleFois(): void
    {
        [$launch] = $this->partie();
        $this->evenement($launch, ['score' => 3, 'total' => 3]);
        $second = $this->evenement($launch, ['score' => 3, 'total' => 3])['gamification'];

        $this->assertSame([], $second['medailles'], 'déjà obtenue');
        $this->assertSame(70, $second['totalXp'], 'les XP, eux, se cumulent');
        $medaille = $this->em->getRepository(Medaille::class)->findOneBy(['libelle' => 'Sans faute '.$this->suffix]);
        $this->assertCount(1, $this->em->getRepository(VisiteurMedaille::class)->findBy(['medaille' => $medaille]));
    }

    public function testConditionNonRemplie(): void
    {
        [$launch] = $this->partie();
        $result = $this->evenement($launch, ['score' => 2, 'total' => 3])['gamification'];
        $this->assertSame([], $result['medailles']);
        $this->assertSame(25, $result['totalXp']);
    }

    public function testRienSansVisiteurIdentifie(): void
    {
        [$launch] = $this->partie(identifie: false);
        $this->assertNull($this->evenement($launch, ['score' => 3, 'total' => 3])['gamification']);
    }

    private function visiteurDe(string $launch): \App\Entity\Visiteur
    {
        $this->em->clear();

        return $this->em->getRepository(LaunchToken::class)->findOneBy(['token' => $launch])->getVisiteur();
    }

    private function demander(string $launch, string $medaille): array
    {
        $this->client->jsonRequest('POST', "/api/launch/$launch/medailles", ['medaille' => $medaille]);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'body' => json_decode($this->client->getResponse()->getContent(), true)];
    }

    /** #8 : médaille demandée par l'interactif, uniquement si ses règles la déclarent attribuable. */
    public function testMedailleDemandeeParLInteractif(): void
    {
        [$launch] = $this->partie();

        $res = $this->demander($launch, 'Sans faute '.$this->suffix);
        $this->assertSame(200, $res['status']);
        $this->assertSame('Sans faute '.$this->suffix, $res['body']['medaille']['libelle']);
        $this->assertTrue($res['body']['nouvelle']);

        // Idempotent : une médaille unique ne s'obtient qu'une fois
        $encore = $this->demander($launch, 'Sans faute '.$this->suffix);
        $this->assertSame([200, false], [$encore['status'], $encore['body']['nouvelle']]);
        // Non unique : au plus une fois par partie
        $this->assertTrue($this->demander($launch, 'Explorateur '.$this->suffix)['body']['nouvelle']);
        $this->assertFalse($this->demander($launch, 'Explorateur '.$this->suffix)['body']['nouvelle']);

        $nb = $this->em->getRepository(VisiteurMedaille::class)->count([
            'medaille' => $this->em->getRepository(Medaille::class)->findOneBy(['libelle' => 'Sans faute '.$this->suffix]),
        ]);
        $this->assertSame(1, $nb);
    }

    public function testMedailleDemandeeRefusee(): void
    {
        [$launch] = $this->partie();
        $reservee = $this->demander($launch, 'Réservée '.$this->suffix);
        $this->assertSame([403, 'MEDAILLE_NON_ATTRIBUABLE'], [$reservee['status'], $reservee['body']['code']]);
        $inconnue = $this->demander($launch, 'Médaille inexistante');
        $this->assertSame([404, 'MEDAILLE_INCONNUE'], [$inconnue['status'], $inconnue['body']['code']]);
        $vide = $this->demander($launch, '  ');
        $this->assertSame([400, 'VALIDATION'], [$vide['status'], $vide['body']['code']]);

        $this->suffix = bin2hex(random_bytes(3)); // autre interactif, autres médailles
        [$anonyme] = $this->partie(false);
        $sansJoueur = $this->demander($anonyme, 'Sans faute '.$this->suffix);
        $this->assertSame([401, 'NON_IDENTIFIE'], [$sansJoueur['status'], $sansJoueur['body']['code']]);
    }
}
