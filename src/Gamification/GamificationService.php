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

    private function alreadyHas(Visiteur $visiteur, Medaille $medaille): bool
    {
        return null !== $this->em->getRepository(VisiteurMedaille::class)->findOneBy(['visiteur' => $visiteur, 'medaille' => $medaille]);
    }
}
