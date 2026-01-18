<?php

namespace App\Entity;

use App\Repository\TypologieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypologieRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'typologie')]
#[ORM\UniqueConstraint(name: 'uniq_typologie_libelle', columns: ['libelle'])]
#[UniqueEntity(
    fields: ['libelle'],
    message: 'Une typologie avec ce libellé existe déjà.'
)]
class Typologie
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
