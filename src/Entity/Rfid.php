<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\RfidRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RfidRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'rfid')]
#[ORM\Index(name: 'idx_rfid_is_resettable', columns: ['is_resettable'])]
class Rfid
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\ManyToOne(targetEntity: RfidGroupe::class)]
    #[ORM\JoinColumn(name: 'groupe_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RfidGroupe $groupe = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $type = null; // admin | animateur | visiteur

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $valeur1 = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $valeur2 = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $valeur3 = '';

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean', name: 'is_resettable', options: ['default' => 1])]
    private bool $isResettable = true;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getGroupe(): ?RfidGroupe { return $this->groupe; }
    public function setGroupe(?RfidGroupe $groupe): self { $this->groupe = $groupe; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): self { $this->type = $type; return $this; }

    public function getValeur1(): string { return $this->valeur1; }
    public function setValeur1(string $v): self { $this->valeur1 = $v; return $this; }

    public function getValeur2(): string { return $this->valeur2; }
    public function setValeur2(string $v): self { $this->valeur2 = $v; return $this; }

    public function getValeur3(): string { return $this->valeur3; }
    public function setValeur3(string $v): self { $this->valeur3 = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $b): self { $this->isActive = $b; return $this; }

    public function isResettable(): bool { return $this->isResettable; }
    public function setIsResettable(bool $b): self { $this->isResettable = $b; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
