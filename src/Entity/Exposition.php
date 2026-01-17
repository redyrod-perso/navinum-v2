<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ExpositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExpositionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'exposition')]
#[ORM\UniqueConstraint(name: 'uniq_exposition_libelle', columns: ['libelle'])]
class Exposition
{
    use EntityTrait;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\ManyToOne(targetEntity: Contexte::class)]
    #[ORM\JoinColumn(name: 'contexte_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contexte $contexte = null;

    #[ORM\ManyToOne(targetEntity: Organisateur::class)]
    #[ORM\JoinColumn(name: 'organisateur_editeur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organisateur $organisateurEditeur = null;

    #[ORM\ManyToOne(targetEntity: Organisateur::class)]
    #[ORM\JoinColumn(name: 'organisateur_diffuseur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organisateur $organisateurDiffuseur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $synopsis = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publics = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $langues = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_illustration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_studio = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $start_at = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $end_at = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    #[ORM\ManyToMany(targetEntity: Parcours::class, mappedBy: 'expositions')]
    private Collection $parcours;

    public function __construct()
    {
        $this->initializeEntity();
        $this->parcours = new ArrayCollection();
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

    public function getContexte(): ?Contexte
    {
        return $this->contexte;
    }

    public function setContexte(?Contexte $contexte): self
    {
        $this->contexte = $contexte;
        return $this;
    }

    public function getOrganisateurEditeur(): ?Organisateur
    {
        return $this->organisateurEditeur;
    }

    public function setOrganisateurEditeur(?Organisateur $organisateur): self
    {
        $this->organisateurEditeur = $organisateur;
        return $this;
    }

    public function getOrganisateurDiffuseur(): ?Organisateur
    {
        return $this->organisateurDiffuseur;
    }

    public function setOrganisateurDiffuseur(?Organisateur $organisateur): self
    {
        $this->organisateurDiffuseur = $organisateur;
        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): self
    {
        $this->synopsis = $synopsis;
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

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): self
    {
        $this->logo = $logo;
        return $this;
    }

    public function getPublics(): ?string
    {
        return $this->publics;
    }

    public function setPublics(?string $publics): self
    {
        $this->publics = $publics;
        return $this;
    }

    public function getLangues(): ?string
    {
        return $this->langues;
    }

    public function setLangues(?string $langues): self
    {
        $this->langues = $langues;
        return $this;
    }

    public function getUrlIllustration(): ?string
    {
        return $this->url_illustration;
    }

    public function setUrlIllustration(?string $url): self
    {
        $this->url_illustration = $url;
        return $this;
    }

    public function getUrlStudio(): ?string
    {
        return $this->url_studio;
    }

    public function setUrlStudio(?string $url): self
    {
        $this->url_studio = $url;
        return $this;
    }

    public function getStartAt(): ?\DateTimeInterface
    {
        return $this->start_at;
    }

    public function setStartAt(?\DateTimeInterface $date): self
    {
        $this->start_at = $date;
        return $this;
    }

    public function getEndAt(): ?\DateTimeInterface
    {
        return $this->end_at;
    }

    public function setEndAt(?\DateTimeInterface $date): self
    {
        $this->end_at = $date;
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
     * @return Collection<int, Parcours>
     */
    public function getParcours(): Collection
    {
        return $this->parcours;
    }

    public function addParcours(Parcours $parcours): self
    {
        if (!$this->parcours->contains($parcours)) {
            $this->parcours->add($parcours);
            $parcours->addExposition($this);
        }

        return $this;
    }

    public function removeParcours(Parcours $parcours): self
    {
        if ($this->parcours->removeElement($parcours)) {
            $parcours->removeExposition($this);
        }

        return $this;
    }
}
