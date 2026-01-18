<?php

namespace App\Entity;

use App\Repository\PreferenceMediaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PreferenceMediaRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'preferencemedia')]
#[ORM\UniqueConstraint(name: 'uniq_preferencemedia_libelle', columns: ['libelle'])]
#[UniqueEntity(
    fields: ['libelle'],
    message: 'Une préférence média avec ce libellé existe déjà.'
)]
class PreferenceMedia
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
        $this->visiteurs = new ArrayCollection();
    }

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le libellé ne peut pas être vide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $libelle = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    #[ORM\ManyToMany(targetEntity: Visiteur::class, mappedBy: 'preferenceMedias')]
    private Collection $visiteurs;

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;
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
     * @return Collection<int, Visiteur>
     */
    public function getVisiteurs(): Collection
    {
        return $this->visiteurs;
    }

    public function addVisiteur(Visiteur $visiteur): self
    {
        if (!$this->visiteurs->contains($visiteur)) {
            $this->visiteurs->add($visiteur);
            $visiteur->addPreferenceMedia($this);
        }

        return $this;
    }

    public function removeVisiteur(Visiteur $visiteur): self
    {
        if ($this->visiteurs->removeElement($visiteur)) {
            $visiteur->removePreferenceMedia($this);
        }

        return $this;
    }
}
