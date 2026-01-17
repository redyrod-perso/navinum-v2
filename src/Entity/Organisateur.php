<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\OrganisateurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrganisateurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'organisateur')]
#[ORM\UniqueConstraint(name: 'uniq_organisateur_libelle', columns: ['libelle'])]
class Organisateur
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function isTosync(): bool
    {
        return $this->is_tosync;
    }

    public function setIsTosync(bool $is_tosync): self
    {
        $this->is_tosync = $is_tosync;
        return $this;
    }
}
