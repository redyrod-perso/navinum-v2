<?php

namespace App\Entity;

use App\Repository\VisiteurMedailleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VisiteurMedailleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'visiteur_medaille')]
class VisiteurMedaille
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le visiteur ne peut pas être null.')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Medaille::class)]
    #[ORM\JoinColumn(name: 'medaille_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La médaille ne peut pas être null.')]
    private ?Medaille $medaille = null;

    #[ORM\Column(length: 255, nullable: false, options: ['default' => ''])]
    private string $connection = '';

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getVisiteur(): ?Visiteur
    {
        return $this->visiteur;
    }

    public function setVisiteur(?Visiteur $visiteur): self
    {
        $this->visiteur = $visiteur;
        return $this;
    }

    public function getMedaille(): ?Medaille
    {
        return $this->medaille;
    }

    public function setMedaille(?Medaille $medaille): self
    {
        $this->medaille = $medaille;
        return $this;
    }

    public function getConnection(): string
    {
        return $this->connection;
    }

    public function setConnection(string $connection): self
    {
        $this->connection = $connection;
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
