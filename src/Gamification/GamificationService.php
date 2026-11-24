<?php

namespace App\Gamification;

use App\Entity\Interactif;
use App\Entity\Medaille;
use App\Entity\Typologie;
use App\Entity\Visiteur;
use App\Entity\VisiteurMedaille;
use App\Entity\Xp;
use App\Service\XpService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applique les règles de gamification d'un interactif à un événement : enregistre les XP
 * (une ligne Xp par gain) et les médailles (VisiteurMedaille), puis renvoie le résumé
 * destiné à l'interactif.
 *
 * Fonctionne entièrement dans Navinum (sans n8n ni Internet). Sans visiteur identifié,
 * rien n'est attribué.
 */
final class GamificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GamificationEngine $engine,
        private readonly XpService $xpService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{xp: list<array{typologie: string, points: int}>, medailles: list<array{libelle: string, description: ?string, image: ?string}>, totalXp: int}|null
     *         null si aucun visiteur n'est identifié ou si l'interactif n'a pas de règles
     */
    public function apply(Interactif $interactif, ?Visiteur $visiteur, string $type, array $payload, string $connection): ?array
    {
        if (null === $visiteur || null === $interactif->getGamification()) {
            return null;
        }

        $awards = $this->engine->evaluate($interactif->getGamification(), $type, $payload);

        $xp = [];
        foreach ($awards['xp'] as $gain) {
            $this->em->persist((new Xp())
                ->setVisiteur($visiteur)
                ->setTypologie($this->typologie($gain['typologie']))
                ->setScore($gain['points']));
            $xp[] = $gain;
        }

        $medailles = [];
        foreach ($awards['medailles'] as $libelle) {
            $medaille = $this->em->getRepository(Medaille::class)->findOneBy(['libelle' => $libelle]);
            if (null === $medaille) {
                $this->logger->warning('Règle de gamification : médaille inconnue', ['medaille' => $libelle, 'interactif' => $interactif->getLibelle()]);
                continue;
            }
            if ($medaille->isUnique() && $this->alreadyHas($visiteur, $medaille)) {
                continue;
            }
            $this->em->persist((new VisiteurMedaille())
                ->setVisiteur($visiteur)
                ->setMedaille($medaille)
                ->setConnection($connection));
            $medailles[] = [
                'libelle' => $medaille->getLibelle(),
                'description' => $medaille->getDescription(),
                'image' => $medaille->getImage(),
            ];
        }

        $this->em->flush();

        return [
            'xp' => $xp,
            'medailles' => $medailles,
            'totalXp' => $this->xpService->getTotalByVisiteur($visiteur->getId())['total'],
        ];
    }

    /** Typologie par libellé, créée si elle n'existe pas encore (ex. « Général »). */
    private function typologie(string $libelle): Typologie
    {
        $repo = $this->em->getRepository(Typologie::class);
        $typologie = $repo->findOneBy(['libelle' => $libelle]);
        if (null === $typologie) {
            foreach ($this->em->getUnitOfWork()->getScheduledEntityInsertions() as $pending) {
                if ($pending instanceof Typologie && $pending->getLibelle() === $libelle) {
                    return $pending;
                }
            }
            $typologie = (new Typologie())->setLibelle($libelle);
            $this->em->persist($typologie);
        }

        return $typologie;
    }

    /**
     * Médaille demandée par l'interactif lui-même (ex. « Explorateur » quand le joueur a tout visité).
     *
     * Anti-triche : seules les médailles listées dans `attribuables` des règles de l'interactif sont accordées.
     * Idempotent : une médaille unique ne s'obtient qu'une fois par visiteur, une autre au plus une fois par
     * partie (`$connection`).
     *
     * @return array{medaille: array{libelle: string, description: ?string, image: ?string}, nouvelle: bool, totalXp: int}
     *
     * @throws MedailleRefusee code MEDAILLE_NON_ATTRIBUABLE ou MEDAILLE_INCONNUE
     */
    public function demanderMedaille(Interactif $interactif, Visiteur $visiteur, string $libelle, string $connection): array
    {
        $attribuables = $interactif->getGamification()['attribuables'] ?? [];
        if (!\is_array($attribuables) || !\in_array($libelle, $attribuables, true)) {
            throw new MedailleRefusee('MEDAILLE_NON_ATTRIBUABLE', \sprintf('La médaille « %s » ne peut pas être attribuée par cet interactif.', $libelle));
        }
        $medaille = $this->em->getRepository(Medaille::class)->findOneBy(['libelle' => $libelle]);
        if (null === $medaille) {
            throw new MedailleRefusee('MEDAILLE_INCONNUE', \sprintf('La médaille « %s » n\'existe pas.', $libelle));
        }

        $deja = $this->em->getRepository(VisiteurMedaille::class)->findOneBy(
            $medaille->isUnique()
                ? ['visiteur' => $visiteur, 'medaille' => $medaille]
                : ['visiteur' => $visiteur, 'medaille' => $medaille, 'connection' => $connection],
        );
        if (null === $deja) {
            $this->em->persist((new VisiteurMedaille())->setVisiteur($visiteur)->setMedaille($medaille)->setConnection($connection));
            $this->em->flush();
        }

        return [
            'medaille' => [
                'libelle' => $medaille->getLibelle(),
                'description' => $medaille->getDescription(),
                'image' => $medaille->getImage(),
            ],
            'nouvelle' => null === $deja,
            'totalXp' => $this->xpService->getTotalByVisiteur($visiteur->getId())['total'],
        ];
    }

    /**
     * Médailles d'un interactif, pour l'afficher au joueur : celles rattachées à l'interactif et celles que ses règles
     * citent (règles de médaille, `attribuables`). Avec un visiteur : obtenue ou non, et depuis quand.
     *
     * @return list<array{libelle: string, description: ?string, image: ?string, condition: ?string, unique: bool,
     *                    attribuable: bool, obtenue: ?bool, obtenueLe: ?string}>
     */
    public function medaillesDe(Interactif $interactif, ?Visiteur $visiteur): array
    {
        $regles = $interactif->getGamification() ?? [];
        $attribuables = array_values(array_filter((array) ($regles['attribuables'] ?? []), 'is_string'));
        $cites = array_merge($attribuables, array_map(
            static fn ($r) => \is_array($r) ? (string) ($r['medaille'] ?? '') : '',
            (array) ($regles['medailles'] ?? []),
        ));

        $repo = $this->em->getRepository(Medaille::class);
        $medailles = [];
        foreach ($repo->findBy(['interactif' => $interactif]) as $medaille) {
            $medailles[$medaille->getLibelle()] = $medaille;
        }
        foreach (array_unique(array_filter($cites)) as $libelle) {
            $medailles[$libelle] ??= $repo->findOneBy(['libelle' => $libelle]);
        }
        $medailles = array_filter($medailles);
        ksort($medailles, \SORT_NATURAL | \SORT_FLAG_CASE);

        $obtenues = [];
        if (null !== $visiteur && [] !== $medailles) {
            foreach ($this->em->getRepository(VisiteurMedaille::class)->findBy(['visiteur' => $visiteur, 'medaille' => array_values($medailles)], ['createdAt' => 'ASC']) as $vm) {
                $obtenues[$vm->getMedaille()->getLibelle()] ??= $vm->getCreatedAt()->format(\DATE_ATOM);
            }
        }

        return array_values(array_map(static fn (Medaille $m) => [
            'libelle' => (string) $m->getLibelle(),
            'description' => $m->getDescription(),
            'image' => $m->getImage(),
            'condition' => $m->getConditionObtention(),
            'unique' => $m->isUnique(),
            'attribuable' => \in_array($m->getLibelle(), $attribuables, true),
            'obtenue' => null === $visiteur ? null : isset($obtenues[$m->getLibelle()]),
            'obtenueLe' => $obtenues[$m->getLibelle()] ?? null,
        ], $medailles));
    }

    private function alreadyHas(Visiteur $visiteur, Medaille $medaille): bool
    {
        return null !== $this->em->getRepository(VisiteurMedaille::class)->findOneBy(['visiteur' => $visiteur, 'medaille' => $medaille]);
    }
}
