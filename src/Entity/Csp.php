<?php

namespace App\Entity;

use App\Repository\CspRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CspRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'csp')]
#[ORM\UniqueConstraint(name: 'uniq_csp_libelle', columns: ['libelle'])]
class Csp
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $l): self { $this->libelle = $l; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
