<?php

namespace App\Entity;

use App\Repository\MedailleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MedailleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'medaille')]
#[ORM\UniqueConstraint(name: 'uniq_medaille_libelle', columns: ['libelle'])]
#[UniqueEntity(
    fields: ['libelle'],
    message: 'Une médaille avec ce libellé existe déjà.'
)]
class Medaille
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToOne(targetEntity: MedailleType::class)]
    #[ORM\JoinColumn(name: 'medaille_type_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?MedailleType $medailleType = null;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(name: 'interactif_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Interactif $interactif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionObtention = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isUnique = true;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getMedailleType(): ?MedailleType
    {
        return $this->medailleType;
    }

    public function setMedailleType(?MedailleType $medailleType): self
    {
        $this->medailleType = $medailleType;
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

    public function getInteractif(): ?Interactif
    {
        return $this->interactif;
    }

    public function setInteractif(?Interactif $interactif): self
    {
        $this->interactif = $interactif;
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

    public function getConditionObtention(): ?string
    {
        return $this->conditionObtention;
    }

    public function setConditionObtention(?string $conditionObtention): self
    {
        $this->conditionObtention = $conditionObtention;
        return $this;
    }

    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    public function setIsUnique(bool $isUnique): self
    {
        $this->isUnique = $isUnique;
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
