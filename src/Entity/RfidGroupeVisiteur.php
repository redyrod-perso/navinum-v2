<?php

namespace App\Entity;

use App\Repository\RfidGroupeVisiteurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RfidGroupeVisiteurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'rfid_groupe_visiteur')]
class RfidGroupeVisiteur
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\ManyToOne(targetEntity: RfidGroupe::class)]
    #[ORM\JoinColumn(name: 'rfid_groupe_id', referencedColumnName: 'id', nullable: false)]
    private RfidGroupe $rfidGroupe;

    #[ORM\ManyToOne(targetEntity: Langue::class)]
    #[ORM\JoinColumn(name: 'langue_id', referencedColumnName: 'id', nullable: false)]
    private Langue $langue;

    #[ORM\ManyToOne(targetEntity: Csp::class)]
    #[ORM\JoinColumn(name: 'csp_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Csp $csp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $age = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $code_postal = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getNom(): string { return $this->nom; }
    public function setNom(string $n): self { $this->nom = $n; return $this; }

    public function getRfidGroupe(): RfidGroupe { return $this->rfidGroupe; }
    public function setRfidGroupe(RfidGroupe $g): self { $this->rfidGroupe = $g; return $this; }

    public function getLangue(): Langue { return $this->langue; }
    public function setLangue(Langue $l): self { $this->langue = $l; return $this; }

    public function getCsp(): ?Csp { return $this->csp; }
    public function setCsp(?Csp $c): self { $this->csp = $c; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $e): self { $this->email = $e; return $this; }

    public function getGenre(): ?string { return $this->genre; }
    public function setGenre(?string $g): self { $this->genre = $g; return $this; }

    public function getAge(): ?int { return $this->age; }
    public function setAge(?int $a): self { $this->age = $a; return $this; }

    public function getCodePostal(): ?int { return $this->code_postal; }
    public function setCodePostal(?int $cp): self { $this->code_postal = $cp; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $c): self { $this->commentaire = $c; return $this; }

    public function isTosync(): bool { return $this->isTosync; }
    public function setIsTosync(bool $b): self { $this->isTosync = $b; return $this; }
}
