<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'notification')]
class Notification
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le visiteur ne peut pas être null.')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Visite::class)]
    #[ORM\JoinColumn(name: 'visite_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La visite ne peut pas être null.')]
    private ?Visite $visite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromModel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fromModelId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parameter = null;

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

    public function getVisiteur(): ?Visiteur
    {
        return $this->visiteur;
    }

    public function setVisiteur(?Visiteur $visiteur): self
    {
        $this->visiteur = $visiteur;
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

    public function getFromModel(): ?string
    {
        return $this->fromModel;
    }

    public function setFromModel(?string $fromModel): self
    {
        $this->fromModel = $fromModel;
        return $this;
    }

    public function getFromModelId(): ?string
    {
        return $this->fromModelId;
    }

    public function setFromModelId(?string $fromModelId): self
    {
        $this->fromModelId = $fromModelId;
        return $this;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }

    public function setParameter(?string $parameter): self
    {
        $this->parameter = $parameter;
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
