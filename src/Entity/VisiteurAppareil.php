<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Appareil sur lequel un visiteur s'est identifié (téléphone, tablette…).
 *
 * L'appareil garde un secret (seule son empreinte SHA-256 est stockée) : en retapant son pseudo sur
 * le même appareil, le visiteur retrouve son profil après expiration de sa session (30 min d'inactivité,
 * 12 h au plus). Révoqué : « Déconnecter partout » (admin) ou déconnexion depuis l'appareil.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'visiteur_appareil')]
#[ORM\Index(name: 'idx_visiteur_appareil_visiteur', columns: ['visiteur_id'])]
class VisiteurAppareil
{
    use EntityTrait;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Visiteur $visiteur;

    /** Empreinte SHA-256 (hex) du secret de l'appareil ; le secret lui-même n'est jamais stocké. */
    #[ORM\Column(length: 64, unique: true)]
    private string $secretHash;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    public function __construct(Visiteur $visiteur, string $secretHash, \DateTimeImmutable $now)
    {
        $this->initializeEntity();
        $this->visiteur = $visiteur;
        $this->secretHash = $secretHash;
        $this->lastSeenAt = $now;
    }

    public function getVisiteur(): Visiteur
    {
        return $this->visiteur;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(\DateTimeImmutable $now): self
    {
        $this->lastSeenAt = $now;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(\DateTimeImmutable $now): self
    {
        $this->revokedAt ??= $now;

        return $this;
    }
}
