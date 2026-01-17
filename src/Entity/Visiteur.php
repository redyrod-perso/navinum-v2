<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\VisiteurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
#[ORM\Table(name: 'visiteur')]
#[ORM\Index(name: 'idx_visiteur_email', columns: ['email'])]
#[ORM\Index(name: 'idx_visiteur_password_son', columns: ['password_son'])]
#[ORM\Index(name: 'idx_visiteur_pseudo_son', columns: ['pseudo_son'])]
class Visiteur
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password_son = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\ManyToOne(targetEntity: Contexte::class)]
    #[ORM\JoinColumn(name: 'contexte_creation_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Contexte $contexteCreation = null;

    #[ORM\ManyToOne(targetEntity: Langue::class)]
    #[ORM\JoinColumn(name: 'langue_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Langue $langue = null;

    #[ORM\Column(length: 32)]
    private string $type = 'visiteur';

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $has_photo = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_naissance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $code_postal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pseudo_son = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $has_newsletter = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url_avatar = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $num_mobile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebook_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $google_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twitter_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flickr_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dailymotion_id = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_anonyme = null;

    #[ORM\ManyToOne(targetEntity: Csp::class)]
    #[ORM\JoinColumn(name: 'csp_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Csp $csp = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_active = true;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;

    public function getPasswordSon(): ?string { return $this->password_son; }
    public function setPasswordSon(?string $p): self { $this->password_son = $p; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): self { $this->email = $e; return $this; }

    public function getContexteCreation(): ?Contexte { return $this->contexteCreation; }
    public function setContexteCreation(?Contexte $c): self { $this->contexteCreation = $c; return $this; }

    public function getLangue(): ?Langue { return $this->langue; }
    public function setLangue(?Langue $l): self { $this->langue = $l; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }

    public function hasPhoto(): bool { return $this->has_photo; }
    public function setHasPhoto(bool $b): self { $this->has_photo = $b; return $this; }

    public function getGenre(): ?string { return $this->genre; }
    public function setGenre(?string $g): self { $this->genre = $g; return $this; }

    public function getDateNaissance(): ?\DateTimeInterface { return $this->date_naissance; }
    public function setDateNaissance(?\DateTimeInterface $d): self { $this->date_naissance = $d; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $a): self { $this->adresse = $a; return $this; }

    public function getCodePostal(): ?string { return $this->code_postal; }
    public function setCodePostal(?string $c): self { $this->code_postal = $c; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $v): self { $this->ville = $v; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(?string $p): self { $this->prenom = $p; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(?string $n): self { $this->nom = $n; return $this; }

    public function getPseudoSon(): ?string { return $this->pseudo_son; }
    public function setPseudoSon(?string $p): self { $this->pseudo_son = $p; return $this; }

    public function getHasNewsletter(): ?bool { return $this->has_newsletter; }
    public function setHasNewsletter(?bool $b): self { $this->has_newsletter = $b; return $this; }

    public function getUrlAvatar(): ?string { return $this->url_avatar; }
    public function setUrlAvatar(?string $u): self { $this->url_avatar = $u; return $this; }

    public function getNumMobile(): ?string { return $this->num_mobile; }
    public function setNumMobile(?string $n): self { $this->num_mobile = $n; return $this; }

    public function getFacebookId(): ?string { return $this->facebook_id; }
    public function setFacebookId(?string $f): self { $this->facebook_id = $f; return $this; }

    public function getGoogleId(): ?string { return $this->google_id; }
    public function setGoogleId(?string $g): self { $this->google_id = $g; return $this; }

    public function getTwitterId(): ?string { return $this->twitter_id; }
    public function setTwitterId(?string $t): self { $this->twitter_id = $t; return $this; }

    public function getFlickrId(): ?string { return $this->flickr_id; }
    public function setFlickrId(?string $f): self { $this->flickr_id = $f; return $this; }

    public function getDailymotionId(): ?string { return $this->dailymotion_id; }
    public function setDailymotionId(?string $d): self { $this->dailymotion_id = $d; return $this; }

    public function isAnonyme(): ?bool { return $this->is_anonyme; }
    public function setIsAnonyme(?bool $b): self { $this->is_anonyme = $b; return $this; }

    public function getCsp(): ?Csp { return $this->csp; }
    public function setCsp(?Csp $c): self { $this->csp = $c; return $this; }

    public function isActive(): bool { return $this->is_active; }
    public function setIsActive(bool $b): self { $this->is_active = $b; return $this; }

    public function isTosync(): bool { return $this->is_tosync; }
    public function setIsTosync(bool $b): self { $this->is_tosync = $b; return $this; }
}
