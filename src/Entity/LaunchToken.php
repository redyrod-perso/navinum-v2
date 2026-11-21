<?php

namespace App\Entity;

use App\Repository\LaunchTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton de lancement d'un interactif.
 *
 * Navinum ouvre `<url de l'interactif>?nv=<token>` ; l'interactif appelle
 * `GET /api/launch/{token}` pour récupérer sa config (et le visiteur s'il est identifié),
 * puis `POST /api/launch/{token}/events` pour remonter ses événements (score…).
 * Les événements sont consignés dans un LogVisite, point d'entrée de la gamification.
 */
#[ORM\Entity(repositoryClass: LaunchTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'launch_token')]
class LaunchToken
{
    use EntityTrait;

    public const DEFAULT_TTL = '+12 hours';

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Interactif $interactif;

    /** Null quand l'interactif est lancé hors parcours (aperçu admin avec la config par défaut). */
    #[ORM\ManyToOne(targetEntity: ParcoursInteractif::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ParcoursInteractif $parcoursInteractif = null;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    /** Null tant que le visiteur est anonyme. */
    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: LogVisite::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LogVisite $logVisite = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(Interactif $interactif, ?ParcoursInteractif $parcoursInteractif = null, string $ttl = self::DEFAULT_TTL)
    {
        $this->initializeEntity();
        $this->token = bin2hex(random_bytes(24));
        $this->interactif = $interactif;
        $this->parcoursInteractif = $parcoursInteractif;
        $this->expiresAt = new \DateTimeImmutable($ttl);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getInteractif(): Interactif
    {
        return $this->interactif;
    }

    public function getParcoursInteractif(): ?ParcoursInteractif
    {
        return $this->parcoursInteractif;
    }

    public function getExposition(): ?Exposition
    {
        return $this->exposition;
    }

    public function setExposition(?Exposition $exposition): self
    {
        $this->exposition = $exposition;

        return $this;
    }

    public function getVisiteur(): ?Visiteur
    {
        return $this->visiteur;
    }

    public function setVisiteur(?Visiteur $visiteur): self
    {
        $this->visiteur = $visiteur;

        return $this;
    }

    public function getLogVisite(): ?LogVisite
    {
        return $this->logVisite;
    }

    public function setLogVisite(?LogVisite $logVisite): self
    {
        $this->logVisite = $logVisite;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) > $this->expiresAt;
    }

    /** Config injectée : celle du placement dans le parcours, ou les défauts de l'interactif. */
    public function getConfig(): array
    {
        return $this->parcoursInteractif?->getEffectiveConfig() ?? $this->interactif->getDefaultConfig();
    }
}
