<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Clé d'intégration (#11) : permet à un outil externe (n8n…) d'agir sur Navinum par l'API (`Authorization:
 * Bearer nvk1.…`), avec des droits limités. Seule l'empreinte SHA-256 du secret est stockée ; le secret n'est
 * affiché qu'une fois, à la création. Révocable.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'cle_integration')]
class CleIntegration
{
    use EntityTrait;

    /** Lire les statistiques (visiteurs, parties, XP, médailles). */
    public const DROIT_STATISTIQUES = 'statistiques';
    /** Attribuer une médaille à un visiteur. */
    public const DROIT_MEDAILLES = 'medailles';
    public const DROITS = [
        self::DROIT_STATISTIQUES => 'Lire les statistiques',
        self::DROIT_MEDAILLES => 'Attribuer des médailles',
    ];

    #[ORM\Column(length: 120)]
    private string $libelle;

    /** Début du secret, pour reconnaître la clé dans l'admin (ex. « nvk1.3f9a… »). */
    #[ORM\Column(length: 16)]
    private string $apercu;

    #[ORM\Column(length: 64, unique: true)]
    private string $secretHash;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $droits = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /** @param list<string> $droits */
    public function __construct(string $libelle, string $apercu, string $secretHash, array $droits)
    {
        $this->initializeEntity();
        $this->libelle = $libelle;
        $this->apercu = $apercu;
        $this->secretHash = $secretHash;
        $this->droits = array_values(array_intersect(array_keys(self::DROITS), $droits));
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getApercu(): string
    {
        return $this->apercu;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    /** @return list<string> */
    public function getDroits(): array
    {
        return $this->droits;
    }

    public function aLeDroit(string $droit): bool
    {
        return \in_array($droit, $this->droits, true);
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function utilisee(\DateTimeImmutable $now): void
    {
        $this->lastUsedAt = $now;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoquer(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}
