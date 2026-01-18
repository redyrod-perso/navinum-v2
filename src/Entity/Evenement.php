<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'evenement')]
#[ORM\UniqueConstraint(name: 'uniq_evenement_libelle', columns: ['libelle'])]
#[UniqueEntity(
    fields: ['libelle'],
    message: 'Un événement avec ce libellé existe déjà.'
)]
class Evenement
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
        $this->expositions = new ArrayCollection();
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de début ne peut pas être null.')]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le libellé ne peut pas être vide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $libelle = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    #[ORM\ManyToMany(targetEntity: Exposition::class)]
    #[ORM\JoinTable(name: 'evenement_exposition')]
    #[ORM\JoinColumn(name: 'evenement_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'exposition_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $expositions;

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(?\DateTimeInterface $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(?\DateTimeInterface $endAt): self
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
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

    /**
     * @return Collection<int, Exposition>
     */
    public function getExpositions(): Collection
    {
        return $this->expositions;
    }

    public function addExposition(Exposition $exposition): self
    {
        if (!$this->expositions->contains($exposition)) {
            $this->expositions->add($exposition);
        }

        return $this;
    }

    public function removeExposition(Exposition $exposition): self
    {
        $this->expositions->removeElement($exposition);
        return $this;
    }
}
