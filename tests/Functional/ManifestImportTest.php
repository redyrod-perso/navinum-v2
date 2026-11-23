<?php

namespace App\Tests\Functional;

use App\Entity\Interactif;
use App\Repository\InteractifRepository;
use App\Service\ManifestImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * #63 : à l'import d'un manifeste, les règles de gamification réglées dans l'admin ne sont jamais modifiées,
 * mais les règles absentes de l'interactif (nouvelle clé du manifeste) sont ajoutées.
 */
class ManifestImportTest extends KernelTestCase
{
    private ManifestImporter $importer;
    private EntityManagerInterface $em;
    private string $s;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->importer = static::getContainer()->get(ManifestImporter::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->s = bin2hex(random_bytes(3));
    }

    private function manifeste(): string
    {
        return json_encode([
            'libelle' => 'Jeu '.$this->s,
            'gamification' => [
                'regles' => [
                    'xp' => [['evenement' => 'jeu.fini', 'points' => 'score * 10']],
                    'medailles' => [['medaille' => 'Rapide '.$this->s, 'evenement' => 'jeu.fini', 'si' => 'duree < 60']],
                    'attribuables' => ['Explorateur '.$this->s],
                    'scoreSession' => ['jeu.fini'],
                ],
                'medailles' => [['libelle' => 'Rapide '.$this->s], ['libelle' => 'Explorateur '.$this->s]],
            ],
        ]);
    }

    public function testNouvelInteractifRecoitToutesLesRegles(): void
    {
        [$interactif, $cree, $rapport] = $this->importer->import($this->manifeste());
        $this->assertTrue($cree);
        $this->assertSame(['xp', 'medailles', 'attribuables', 'scoreSession'], $rapport['reglesAjoutees']);
        $this->assertSame(['Rapide '.$this->s, 'Explorateur '.$this->s], $rapport['medaillesCreees']);
        $this->assertSame(['jeu.fini'], $interactif->getGamification()['scoreSession']);
    }

    public function testReglesDeLAdminConserveesNouvellesReglesAjoutees(): void
    {
        $reglesAdmin = ['xp' => [['evenement' => 'jeu.fini', 'points' => '5']], 'medailles' => []];
        $this->em->persist((new Interactif())->setLibelle('Jeu '.$this->s)->setGamification($reglesAdmin));
        $this->em->flush();

        [$interactif, $cree, $rapport] = $this->importer->import($this->manifeste());
        $this->assertFalse($cree);
        $this->assertSame(['attribuables', 'scoreSession'], $rapport['reglesAjoutees']);
        $regles = $interactif->getGamification();
        $this->assertSame($reglesAdmin['xp'], $regles['xp'], 'règles XP de l\'admin intactes');
        $this->assertSame([], $regles['medailles'], 'liste vide laissée volontairement : conservée');
        $this->assertSame(['jeu.fini'], $regles['scoreSession']);

        // Nouvel import : plus rien à ajouter
        $this->assertSame([], $this->importer->import($this->manifeste())[2]['reglesAjoutees']);
    }

    public function testSimulationNEnregistreRien(): void
    {
        [$interactif, $cree, $rapport] = $this->importer->import($this->manifeste(), null, true);
        $this->assertNull($interactif);
        $this->assertTrue($cree);
        $this->assertCount(4, $rapport['reglesAjoutees']);
        $this->assertNull(static::getContainer()->get(InteractifRepository::class)->findOneBy(['libelle' => 'Jeu '.$this->s]));
    }

    public function testCommandeDryRun(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'manifeste');
        file_put_contents($fichier, $this->manifeste());
        $commande = new CommandTester((new Application(self::$kernel))->find('app:interactif:import'));

        $this->assertSame(0, $commande->execute(['manifest' => $fichier, '--dry-run' => true]));
        $affichage = preg_replace('/[\s!]+/', ' ', $commande->getDisplay()); // retours à la ligne de la console
        $this->assertStringContainsString('Rien n\'a été enregistré', $affichage);
        $this->assertStringContainsString('scoreSession', $affichage);
        $this->assertNull(static::getContainer()->get(InteractifRepository::class)->findOneBy(['libelle' => 'Jeu '.$this->s]));

        $this->assertSame(0, $commande->execute(['manifest' => $fichier]));
        $this->assertStringContainsString('créé', $commande->getDisplay());
        unlink($fichier);
    }
}
