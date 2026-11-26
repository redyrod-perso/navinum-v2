<?php

namespace App\Security;

use App\Entity\CleIntegration;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Outil externe authentifié par une clé d'intégration : ROLE_INTEGRATION + un rôle par droit
 * (ex. ROLE_INTEGRATION_MEDAILLES), pour `#[IsGranted]` sur les routes.
 */
final class IntegrationUser implements UserInterface
{
    public function __construct(private readonly CleIntegration $cle)
    {
    }

    public function getCle(): CleIntegration
    {
        return $this->cle;
    }

    public function getRoles(): array
    {
        return ['ROLE_INTEGRATION', ...array_map(static fn (string $d) => 'ROLE_INTEGRATION_'.strtoupper($d), $this->cle->getDroits())];
    }

    public function getUserIdentifier(): string
    {
        return 'integration:'.$this->cle->getId()->toRfc4122();
    }

    public function eraseCredentials(): void
    {
    }
}
