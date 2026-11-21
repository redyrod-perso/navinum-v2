<?php

namespace App\Controller\Admin;

use App\Entity\Exposition;
use App\Entity\Organisateur;
use App\Entity\Parcours;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;

/**
 * Restreint ce que voit un ROLE_ORGANISATEUR aux expositions de son organisateur
 * (éditeur ou diffuseur). Un ROLE_ADMIN voit tout.
 */
trait OrganisateurScopeTrait
{
    protected function isFullAdmin(): bool
    {
        return $this->isGranted(User::ROLE_ADMIN);
    }

    protected function currentOrganisateur(): ?Organisateur
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getOrganisateur() : null;
    }

    /** Ajoute la restriction sur un alias d'exposition. */
    protected function scopeExpositions(QueryBuilder $qb, string $alias): QueryBuilder
    {
        if ($this->isFullAdmin()) {
            return $qb;
        }
        $org = $this->currentOrganisateur();
        if (null === $org) {
            return $qb->andWhere('1 = 0');
        }

        return $qb->andWhere("$alias.organisateurEditeur = :scope_org OR $alias.organisateurDiffuseur = :scope_org")
            ->setParameter('scope_org', $org->getId(), 'uuid');
    }

    protected function canManageExposition(Exposition $exposition): bool
    {
        if ($this->isFullAdmin()) {
            return true;
        }
        $org = $this->currentOrganisateur();

        return null !== $org && ($exposition->getOrganisateurEditeur() === $org || $exposition->getOrganisateurDiffuseur() === $org);
    }

    /** Un parcours est gérable s'il n'est rattaché à aucune exposition (admin seulement) ou à une exposition gérable. */
    protected function canManageParcours(Parcours $parcours): bool
    {
        if ($this->isFullAdmin()) {
            return true;
        }
        foreach ($parcours->getExpositions() as $exposition) {
            if ($this->canManageExposition($exposition)) {
                return true;
            }
        }

        return false;
    }
}
