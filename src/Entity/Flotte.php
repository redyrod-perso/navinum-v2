<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FlotteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlotteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'flotte')]
#[ORM\UniqueConstraint(name: 'uniq_flotte_libelle', columns: ['libelle'])]
class Flotte
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $l): self { $this->libelle = $l; return $this; }

    public function getExposition(): ?Exposition { return $this->exposition; }
    public function setExposition(?Exposition $e): self { $this->exposition = $e; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
