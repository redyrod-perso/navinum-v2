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

    /** @var Collection<int, ParcoursInteractif> */
    #[ORM\OneToMany(targetEntity: ParcoursInteractif::class, mappedBy: 'parcours', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $parcoursInteractifs;

    public function __construct()
    {
        $this->initializeEntity();
        $this->expositions = new ArrayCollection();
        $this->parcoursInteractifs = new ArrayCollection();
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
     * Interactifs du parcours, triés par ordre, avec leur configuration.
     *
     * @return Collection<int, ParcoursInteractif>
     */
    public function getParcoursInteractifs(): Collection
    {
        return $this->parcoursInteractifs;
    }

    /** Ajoute un interactif en fin de parcours et renvoie le placement créé. */
    public function addInteractif(Interactif $interactif, ?array $config = null): ParcoursInteractif
    {
        $max = -1;
        foreach ($this->parcoursInteractifs as $pi) {
            $max = max($max, $pi->getOrdre());
        }
        $placement = (new ParcoursInteractif($this, $interactif))
            ->setOrdre($max + 1)
            ->setConfig($config);
        $this->parcoursInteractifs->add($placement);

        return $placement;
    }

    public function removeParcoursInteractif(ParcoursInteractif $placement): self
    {
        $this->parcoursInteractifs->removeElement($placement);

        return $this;
    }

    /** @return list<Interactif> */
    public function getInteractifs(): array
    {
        return array_values(array_map(
            static fn (ParcoursInteractif $pi) => $pi->getInteractif(),
            $this->parcoursInteractifs->toArray(),
        ));
    }
}
