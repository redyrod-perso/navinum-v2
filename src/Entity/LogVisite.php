<?php

namespace App\Entity;

use App\Repository\LogVisiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogVisiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'log_visite')]
class LogVisite
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(name: 'interactif_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Interactif $interactif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $interactif_libelle = null;

    #[ORM\ManyToOne(targetEntity: Visite::class)]
    #[ORM\JoinColumn(name: 'visite_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Visite $visite = null;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $connection = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $start_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $end_at = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultats = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    public function getInteractif(): ?Interactif
    {
        return $this->interactif;
    }

    public function setInteractif(?Interactif $interactif): self
    {
        $this->interactif = $interactif;
        return $this;
    }

    public function getInteractifLibelle(): ?string
    {
        return $this->interactif_libelle;
    }

    public function setInteractifLibelle(?string $interactif_libelle): self
    {
        $this->interactif_libelle = $interactif_libelle;
        return $this;
    }

    public function getVisite(): ?Visite
    {
        return $this->visite;
    }

    public function setVisite(?Visite $visite): self
    {
        $this->visite = $visite;
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

    public function getExposition(): ?Exposition
    {
        return $this->exposition;
    }

    public function setExposition(?Exposition $exposition): self
    {
        $this->exposition = $exposition;
        return $this;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function getStartAt(): \DateTimeInterface
    {
        return $this->start_at;
    }

    public function setStartAt(\DateTimeInterface $start_at): self
    {
        $this->start_at = $start_at;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->end_at;
    }

    public function setEndAt(?\DateTimeInterface $end_at): self
    {
        $this->end_at = $end_at;
        return $this;
    }

    public function getResultats(): ?string
    {
        return $this->resultats;
    }

    public function setResultats(?string $resultats): self
    {
        $this->resultats = $resultats;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getIsTosync(): bool
    {
        return $this->is_tosync;
    }

    public function setIsTosync(bool $is_tosync): self
    {
        $this->is_tosync = $is_tosync;
        return $this;
    }
}
