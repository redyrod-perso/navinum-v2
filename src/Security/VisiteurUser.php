<?php

namespace App\Security;

use App\Entity\Visiteur;
use Symfony\Component\Security\Core\User\UserInterface;

/** Visiteur authentifié par son jeton (Bearer `nvv2.…`) sur le pare-feu `api` : rôle ROLE_VISITEUR. */
final class VisiteurUser implements UserInterface
{
    public function __construct(private readonly Visiteur $visiteur)
    {
    }

    public function getVisiteur(): Visiteur
    {
        return $this->visiteur;
    }

    public function getRoles(): array
    {
        return ['ROLE_VISITEUR'];
    }

    public function getUserIdentifier(): string
    {
        return 'visiteur:'.$this->visiteur->getId()->toRfc4122();
    }

    public function eraseCredentials(): void
    {
    }
}
