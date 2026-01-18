<?php

namespace App\Entity;

use App\Repository\MedailleTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MedailleTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'medaille_type')]
class MedailleType
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le libellé ne peut pas être vide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isTosync(): bool
    {
        return $this->isTosync;
    }

    public function setIsTosync(bool $isTosync): self
    {
        $this->isTosync = $isTosync;
        return $this;
    }
}
