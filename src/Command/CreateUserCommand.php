<?php

namespace App\Command;

use App\Entity\Organisateur;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée (ou met à jour) un compte de l'admin.
 *
 *   bin/console app:user:create admin@exemple.fr --admin
 *   bin/console app:user:create musee@exemple.fr --organisateur="Musée X"
 *
 * Le mot de passe est demandé de façon masquée (ou passé via --password pour les scripts).
 */
#[AsCommand(name: 'app:user:create', description: "Crée ou met à jour un compte de l'espace d'administration")]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne le rôle ROLE_ADMIN (sinon ROLE_ORGANISATEUR)')
            ->addOption('organisateur', null, InputOption::VALUE_REQUIRED, "Libellé de l'organisateur rattaché (créé s'il n'existe pas)")
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom affiché')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (sinon demandé interactivement)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim($input->getArgument('email')));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');

            return Command::INVALID;
        }

        $password = $input->getOption('password') ?? $io->askHidden('Mot de passe (8 caractères minimum)');
        if (!\is_string($password) || mb_strlen($password) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return Command::INVALID;
        }

        $user = $this->users->findOneBy(['email' => $email]);
        $created = null === $user;
        $user ??= (new User())->setEmail($email);

        $user->setRoles([$input->getOption('admin') ? User::ROLE_ADMIN : User::ROLE_ORGANISATEUR]);
        if ($nom = $input->getOption('nom')) {
            $user->setNom($nom);
        }
        if ($libelle = $input->getOption('organisateur')) {
            $organisateur = $this->em->getRepository(Organisateur::class)->findOneBy(['libelle' => $libelle]);
            if (null === $organisateur) {
                $organisateur = (new Organisateur())->setLibelle($libelle);
                $this->em->persist($organisateur);
                $io->note("Organisateur « $libelle » créé.");
            }
            $user->setOrganisateur($organisateur);
        }
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setIsActive(true);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('Compte %s %s (%s).', $email, $created ? 'créé' : 'mis à jour', implode(', ', $user->getStoredRoles())));

        return Command::SUCCESS;
    }
}
