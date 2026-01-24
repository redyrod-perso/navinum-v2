<?php

namespace App\Entity;

use App\Repository\ParcoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParcoursRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'parcours')]
#[ORM\UniqueConstraint(name: 'uniq_parcours_libelle', columns: ['libelle'])]
class Parcours
{
    use EntityTrait;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ordre = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    #[ORM\ManyToMany(targetEntity: Exposition::class, inversedBy: 'parcours')]
    #[ORM\JoinTable(name: 'exposition_parcours')]
    #[ORM\JoinColumn(name: 'parcours_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'exposition_id', referencedColumnName: 'id')]
    private Collection $expositions;

    #[ORM\ManyToMany(targetEntity: Interactif::class, inversedBy: 'parcours')]
    #[ORM\JoinTable(name: 'parcours_interactif')]
    #[ORM\JoinColumn(name: 'parcours_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'interactif_id', referencedColumnName: 'id')]
    private Collection $interactifs;

    public function __construct()
    {
        $this->initializeEntity();
        $this->expositions = new ArrayCollection();
        $this->interactifs = new ArrayCollection();
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): self
    {
        $this->ordre = $ordre;
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

    /**
     * @return Collection<int, Interactif>
     */
    public function getInteractifs(): Collection
    {
        return $this->interactifs;
    }

    public function addInteractif(Interactif $interactif): self
    {
        if (!$this->interactifs->contains($interactif)) {
            $this->interactifs->add($interactif);
        }

        return $this;
    }

    public function removeInteractif(Interactif $interactif): self
    {
        $this->interactifs->removeElement($interactif);

        return $this;
    }
}
