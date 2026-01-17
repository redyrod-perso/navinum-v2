<?php

namespace App\Entity;

use App\Repository\LangueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LangueRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'langue')]
#[ORM\UniqueConstraint(name: 'uniq_langue_libelle', columns: ['libelle'])]
class Langue
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 128)]
    private string $libelle;

    #[ORM\Column(length: 10)]
    private string $shortLibelle;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $l): self { $this->libelle = $l; return $this; }

    public function getShortLibelle(): string { return $this->shortLibelle; }
    public function setShortLibelle(string $s): self { $this->shortLibelle = $s; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
