<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\InteractifRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InteractifRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'interactif')]
#[ORM\UniqueConstraint(name: 'uniq_interactif_libelle', columns: ['libelle'])]
class Interactif
{
    use EntityTrait;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\ManyToMany(targetEntity: Parcours::class, mappedBy: 'interactifs')]
    private Collection $parcours;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $source_type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $synopsis = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $editeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publics = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $markets = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_market_ios = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_market_android = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_market_windows = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $langues = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image3 = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_diff = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explications_resultats = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $score = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $variable = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $url_scheme = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_fichier_interactif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_pierre_de_rosette = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_illustration = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $url_interactif_type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_interactif_choice = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $url_visiteur_type = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $url_start_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_start_at_type = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $url_end_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_end_at_type = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $refresh_deploiement = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_visiteur_needed = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_logvisite_needed = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_logvisite_verbose_needed = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_parcours_needed = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $ordre = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    public function __construct()
    {
        $this->initializeEntity();
        $this->parcours = new ArrayCollection();
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function isVisiteurNeeded(): ?bool
    {
        return $this->is_visiteur_needed;
    }

    public function setIsVisiteurNeeded(bool $is_visiteur_needed): static
    {
        $this->is_visiteur_needed = $is_visiteur_needed;

        return $this;
    }

    public function isLogvisiteNeeded(): ?bool
    {
        return $this->is_logvisite_needed;
    }

    public function setIsLogvisiteNeeded(bool $is_logvisite_needed): static
    {
        $this->is_logvisite_needed = $is_logvisite_needed;

        return $this;
    }

    public function isParcoursNeeded(): ?bool
    {
        return $this->is_parcours_needed;
    }

    public function setIsParcoursNeeded(bool $is_parcours_needed): static
    {
        $this->is_parcours_needed = $is_parcours_needed;

        return $this;
    }

    public function isTosync(): ?bool
    {
        return $this->is_tosync;
    }

    public function setIsTosync(bool $is_tosync): static
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
            $parcours->addInteractif($this);
        }

        return $this;
    }

    public function removeParcours(Parcours $parcours): self
    {
        if ($this->parcours->removeElement($parcours)) {
            $parcours->removeInteractif($this);
        }

        return $this;
    }

    // Additional getters/setters can be added later as needed

    public function getSourceType(): ?string
    {
        return $this->source_type;
    }

    public function setSourceType(?string $source_type): static
    {
        $this->source_type = $source_type;

        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): static
    {
        $this->synopsis = $synopsis;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getEditeur(): ?string
    {
        return $this->editeur;
    }

    public function setEditeur(?string $editeur): static
    {
        $this->editeur = $editeur;

        return $this;
    }

    public function getPublics(): ?string
    {
        return $this->publics;
    }

    public function setPublics(?string $publics): static
    {
        $this->publics = $publics;

        return $this;
    }

    public function getMarkets(): ?string
    {
        return $this->markets;
    }

    public function setMarkets(?string $markets): static
    {
        $this->markets = $markets;

        return $this;
    }

    public function getUrlMarketIos(): ?string
    {
        return $this->url_market_ios;
    }

    public function setUrlMarketIos(?string $url_market_ios): static
    {
        $this->url_market_ios = $url_market_ios;

        return $this;
    }

    public function getUrlMarketAndroid(): ?string
    {
        return $this->url_market_android;
    }

    public function setUrlMarketAndroid(?string $url_market_android): static
    {
        $this->url_market_android = $url_market_android;

        return $this;
    }

    public function getUrlMarketWindows(): ?string
    {
        return $this->url_market_windows;
    }

    public function setUrlMarketWindows(?string $url_market_windows): static
    {
        $this->url_market_windows = $url_market_windows;

        return $this;
    }

    public function getLangues(): ?string
    {
        return $this->langues;
    }

    public function setLangues(?string $langues): static
    {
        $this->langues = $langues;

        return $this;
    }

    public function getImage1(): ?string
    {
        return $this->image1;
    }

    public function setImage1(?string $image1): static
    {
        $this->image1 = $image1;

        return $this;
    }

    public function getImage2(): ?string
    {
        return $this->image2;
    }

    public function setImage2(?string $image2): static
    {
        $this->image2 = $image2;

        return $this;
    }

    public function getImage3(): ?string
    {
        return $this->image3;
    }

    public function setImage3(?string $image3): static
    {
        $this->image3 = $image3;

        return $this;
    }

    public function getDateDiff(): ?\DateTime
    {
        return $this->date_diff;
    }

    public function setDateDiff(?\DateTime $date_diff): static
    {
        $this->date_diff = $date_diff;

        return $this;
    }

    public function getExplicationsResultats(): ?string
    {
        return $this->explications_resultats;
    }

    public function setExplicationsResultats(?string $explications_resultats): static
    {
        $this->explications_resultats = $explications_resultats;

        return $this;
    }

    public function getScore(): ?string
    {
        return $this->score;
    }

    public function setScore(?string $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getVariable(): ?string
    {
        return $this->variable;
    }

    public function setVariable(?string $variable): static
    {
        $this->variable = $variable;

        return $this;
    }

    public function getUrlScheme(): ?string
    {
        return $this->url_scheme;
    }

    public function setUrlScheme(?string $url_scheme): static
    {
        $this->url_scheme = $url_scheme;

        return $this;
    }

    public function getUrlFichierInteractif(): ?string
    {
        return $this->url_fichier_interactif;
    }

    public function setUrlFichierInteractif(?string $url_fichier_interactif): static
    {
        $this->url_fichier_interactif = $url_fichier_interactif;

        return $this;
    }

    public function getUrlPierreDeRosette(): ?string
    {
        return $this->url_pierre_de_rosette;
    }

    public function setUrlPierreDeRosette(?string $url_pierre_de_rosette): static
    {
        $this->url_pierre_de_rosette = $url_pierre_de_rosette;

        return $this;
    }

    public function getUrlIllustration(): ?string
    {
        return $this->url_illustration;
    }

    public function setUrlIllustration(?string $url_illustration): static
    {
        $this->url_illustration = $url_illustration;

        return $this;
    }

    public function getUrlInteractifType(): ?int
    {
        return $this->url_interactif_type;
    }

    public function setUrlInteractifType(?int $url_interactif_type): static
    {
        $this->url_interactif_type = $url_interactif_type;

        return $this;
    }

    public function getUrlInteractifChoice(): ?string
    {
        return $this->url_interactif_choice;
    }

    public function setUrlInteractifChoice(?string $url_interactif_choice): static
    {
        $this->url_interactif_choice = $url_interactif_choice;

        return $this;
    }

    public function getUrlVisiteurType(): ?int
    {
        return $this->url_visiteur_type;
    }

    public function setUrlVisiteurType(?int $url_visiteur_type): static
    {
        $this->url_visiteur_type = $url_visiteur_type;

        return $this;
    }

    public function getUrlStartAt(): ?int
    {
        return $this->url_start_at;
    }

    public function setUrlStartAt(?int $url_start_at): static
    {
        $this->url_start_at = $url_start_at;

        return $this;
    }

    public function getUrlStartAtType(): ?string
    {
        return $this->url_start_at_type;
    }

    public function setUrlStartAtType(?string $url_start_at_type): static
    {
        $this->url_start_at_type = $url_start_at_type;

        return $this;
    }

    public function getUrlEndAt(): ?int
    {
        return $this->url_end_at;
    }

    public function setUrlEndAt(?int $url_end_at): static
    {
        $this->url_end_at = $url_end_at;

        return $this;
    }

    public function getUrlEndAtType(): ?string
    {
        return $this->url_end_at_type;
    }

    public function setUrlEndAtType(?string $url_end_at_type): static
    {
        $this->url_end_at_type = $url_end_at_type;

        return $this;
    }

    public function isRefreshDeploiement(): ?bool
    {
        return $this->refresh_deploiement;
    }

    public function setRefreshDeploiement(bool $refresh_deploiement): static
    {
        $this->refresh_deploiement = $refresh_deploiement;

        return $this;
    }

    public function isLogvisiteVerboseNeeded(): ?bool
    {
        return $this->is_logvisite_verbose_needed;
    }

    public function setIsLogvisiteVerboseNeeded(bool $is_logvisite_verbose_needed): static
    {
        $this->is_logvisite_verbose_needed = $is_logvisite_verbose_needed;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function addParcour(Parcours $parcour): static
    {
        if (!$this->parcours->contains($parcour)) {
            $this->parcours->add($parcour);
            $parcour->addInteractif($this);
        }

        return $this;
    }

    public function removeParcour(Parcours $parcour): static
    {
        if ($this->parcours->removeElement($parcour)) {
            $parcour->removeInteractif($this);
        }

        return $this;
    }
}
