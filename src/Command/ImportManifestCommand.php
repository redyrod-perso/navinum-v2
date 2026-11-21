<?php

namespace App\Command;

use App\Service\ManifestImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enregistre ou met à jour un interactif depuis son manifeste.
 *
 *   bin/console app:interactif:import public/quiz/navinum.json
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
        $this->addArgument('manifest', InputArgument::REQUIRED, 'Chemin du fichier navinum.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('manifest');
        if (!is_file($path) || !is_readable($path)) {
            $io->error("Fichier introuvable : $path");

            return Command::INVALID;
        }

        try {
            [$interactif, $created] = $this->importer->import((string) file_get_contents($path));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Interactif « %s » %s (%s).', $interactif->getLibelle(), $created ? 'créé' : 'mis à jour', $interactif->getId()));

        return Command::SUCCESS;
    }
}
