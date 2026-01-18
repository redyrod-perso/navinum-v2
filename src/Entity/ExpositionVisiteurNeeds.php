<?php

namespace App\Entity;

use App\Repository\ExpositionVisiteurNeedsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpositionVisiteurNeedsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'exposition_visiteurneeds')]
class ExpositionVisiteurNeeds
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
        $this->langues = new ArrayCollection();
        $this->preferenceMedias = new ArrayCollection();
    }

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'exposition ne peut pas être null.')]
    private ?Exposition $exposition = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasGenre = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasDateNaissance = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasCodePostal = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasVille = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasAdresse = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasPrenom = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasNom = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasCsp = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasNumMobile = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasFacebookId = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasGoogleId = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasTwitterId = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasFlickrId = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $hasDailymotionId = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    #[ORM\ManyToMany(targetEntity: Langue::class)]
    #[ORM\JoinTable(name: 'langue_exposition_visiteurneeds')]
    #[ORM\JoinColumn(name: 'exposition_visiteurneeds_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'langue_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $langues;

    #[ORM\ManyToMany(targetEntity: PreferenceMedia::class)]
    #[ORM\JoinTable(name: 'preferencemedia_exposition_visiteurneeds')]
    #[ORM\JoinColumn(name: 'exposition_visiteurneeds_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'preference_media_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $preferenceMedias;

    public function getExposition(): ?Exposition
    {
        return $this->exposition;
    }

    public function setExposition(?Exposition $exposition): self
    {
        $this->exposition = $exposition;
        return $this;
    }

    public function hasGenre(): bool
    {
        return $this->hasGenre;
    }

    public function setHasGenre(bool $hasGenre): self
    {
        $this->hasGenre = $hasGenre;
        return $this;
    }

    public function hasDateNaissance(): bool
    {
        return $this->hasDateNaissance;
    }

    public function setHasDateNaissance(bool $hasDateNaissance): self
    {
        $this->hasDateNaissance = $hasDateNaissance;
        return $this;
    }

    public function hasCodePostal(): bool
    {
        return $this->hasCodePostal;
    }

    public function setHasCodePostal(bool $hasCodePostal): self
    {
        $this->hasCodePostal = $hasCodePostal;
        return $this;
    }

    public function hasVille(): bool
    {
        return $this->hasVille;
    }

    public function setHasVille(bool $hasVille): self
    {
        $this->hasVille = $hasVille;
        return $this;
    }

    public function hasAdresse(): bool
    {
        return $this->hasAdresse;
    }

    public function setHasAdresse(bool $hasAdresse): self
    {
        $this->hasAdresse = $hasAdresse;
        return $this;
    }

    public function hasPrenom(): bool
    {
        return $this->hasPrenom;
    }

    public function setHasPrenom(bool $hasPrenom): self
    {
        $this->hasPrenom = $hasPrenom;
        return $this;
    }

    public function hasNom(): bool
    {
        return $this->hasNom;
    }

    public function setHasNom(bool $hasNom): self
    {
        $this->hasNom = $hasNom;
        return $this;
    }

    public function hasCsp(): bool
    {
        return $this->hasCsp;
    }

    public function setHasCsp(bool $hasCsp): self
    {
        $this->hasCsp = $hasCsp;
        return $this;
    }

    public function hasNumMobile(): bool
    {
        return $this->hasNumMobile;
    }

    public function setHasNumMobile(bool $hasNumMobile): self
    {
        $this->hasNumMobile = $hasNumMobile;
        return $this;
    }

    public function hasFacebookId(): bool
    {
        return $this->hasFacebookId;
    }

    public function setHasFacebookId(bool $hasFacebookId): self
    {
        $this->hasFacebookId = $hasFacebookId;
        return $this;
    }

    public function hasGoogleId(): bool
    {
        return $this->hasGoogleId;
    }

    public function setHasGoogleId(bool $hasGoogleId): self
    {
        $this->hasGoogleId = $hasGoogleId;
        return $this;
    }

    public function hasTwitterId(): bool
    {
        return $this->hasTwitterId;
    }

    public function setHasTwitterId(bool $hasTwitterId): self
    {
        $this->hasTwitterId = $hasTwitterId;
        return $this;
    }

    public function hasFlickrId(): bool
    {
        return $this->hasFlickrId;
    }

    public function setHasFlickrId(bool $hasFlickrId): self
    {
        $this->hasFlickrId = $hasFlickrId;
        return $this;
    }

    public function hasDailymotionId(): bool
    {
        return $this->hasDailymotionId;
    }

    public function setHasDailymotionId(bool $hasDailymotionId): self
    {
        $this->hasDailymotionId = $hasDailymotionId;
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
     * @return Collection<int, Langue>
     */
    public function getLangues(): Collection
    {
        return $this->langues;
    }

    public function addLangue(Langue $langue): self
    {
        if (!$this->langues->contains($langue)) {
            $this->langues->add($langue);
        }

        return $this;
    }

    public function removeLangue(Langue $langue): self
    {
        $this->langues->removeElement($langue);
        return $this;
    }

    /**
     * @return Collection<int, PreferenceMedia>
     */
    public function getPreferenceMedias(): Collection
    {
        return $this->preferenceMedias;
    }

    public function addPreferenceMedia(PreferenceMedia $preferenceMedia): self
    {
        if (!$this->preferenceMedias->contains($preferenceMedia)) {
            $this->preferenceMedias->add($preferenceMedia);
        }

        return $this;
    }

    public function removePreferenceMedia(PreferenceMedia $preferenceMedia): self
    {
        $this->preferenceMedias->removeElement($preferenceMedia);
        return $this;
    }
}
