<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\PeripheriqueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PeripheriqueRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'peripherique')]
#[ORM\UniqueConstraint(name: 'uniq_peripherique_adresse_mac', columns: ['adresse_mac'])]
class Peripherique
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 64)]
    private string $adresse_mac;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $adresse_ip = null;

    #[ORM\ManyToOne(targetEntity: Flotte::class)]
    #[ORM\JoinColumn(name: 'flotte_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Flotte $flotte = null;

    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(name: 'interactif_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Interactif $interactif;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serial_number = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getAdresseMac(): string { return $this->adresse_mac; }
    public function setAdresseMac(string $m): self { $this->adresse_mac = $m; return $this; }

    public function getAdresseIp(): ?string { return $this->adresse_ip; }
    public function setAdresseIp(?string $ip): self { $this->adresse_ip = $ip; return $this; }

    public function getFlotte(): ?Flotte { return $this->flotte; }
    public function setFlotte(?Flotte $f): self { $this->flotte = $f; return $this; }

    public function getInteractif(): Interactif { return $this->interactif; }
    public function setInteractif(Interactif $i): self { $this->interactif = $i; return $this; }

    public function getSerialNumber(): ?string { return $this->serial_number; }
    public function setSerialNumber(?string $s): self { $this->serial_number = $s; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
