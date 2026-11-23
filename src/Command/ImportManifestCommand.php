<?php

namespace App\Command;

use App\Service\ManifestImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enregistre ou met à jour un interactif depuis son manifeste.
 *
 *   bin/console app:interactif:import public/quiz/navinum.json
 *   bin/console app:interactif:import public/quiz/navinum.json --dry-run   # rapport seul, rien n'est enregistré
 *
 * Les règles de gamification déjà réglées dans l'admin ne sont jamais modifiées : seules les règles absentes
 * de l'interactif sont ajoutées (voir ManifestImporter).
 */
#[AsCommand(name: 'app:interactif:import', description: 'Enregistre un interactif depuis son manifeste navinum.json')]
final class ImportManifestCommand extends Command
{
    public function __construct(private readonly ManifestImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('manifest', InputArgument::REQUIRED, 'Chemin du fichier navinum.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait fait, sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('manifest');
        if (!is_file($path) || !is_readable($path)) {
            $io->error("Fichier introuvable : $path");

            return Command::INVALID;
        }

        $simulation = (bool) $input->getOption('dry-run');
        try {
            [$interactif, $created, $rapport] = $this->importer->import((string) file_get_contents($path), null, $simulation);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $lignes = [
            'Règles ajoutées : '.([] === $rapport['reglesAjoutees'] ? 'aucune (règles existantes conservées)' : implode(', ', $rapport['reglesAjoutees'])),
            'Médailles créées : '.([] === $rapport['medaillesCreees'] ? 'aucune' : implode(', ', $rapport['medaillesCreees'])),
        ];
        if ($simulation) {
            $io->note(array_merge([\sprintf('Simulation : l\'interactif « %s » serait %s. Rien n\'a été enregistré.', $rapport['libelle'], $created ? 'créé' : 'mis à jour')], $lignes));

            return Command::SUCCESS;
        }
        $io->success(array_merge([\sprintf('Interactif « %s » %s (%s).', $interactif->getLibelle(), $created ? 'créé' : 'mis à jour', $interactif->getId())], $lignes));

        return Command::SUCCESS;
    }
}
