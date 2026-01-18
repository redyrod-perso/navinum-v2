<?php

namespace App\Entity;

use App\Repository\XpRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: XpRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'xp')]
class Xp
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotBlank(message: 'Le score ne peut pas être vide.')]
    private ?int $score = null;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le visiteur ne peut pas être null.')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Typologie::class)]
    #[ORM\JoinColumn(name: 'typologie_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La typologie ne peut pas être null.')]
    private ?Typologie $typologie = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getVisiteur(): ?Visiteur
    {
        return $this->visiteur;
    }

    public function setVisiteur(?Visiteur $visiteur): self
    {
        $this->visiteur = $visiteur;
        return $this;
    }

    public function getTypologie(): ?Typologie
    {
        return $this->typologie;
    }

    public function setTypologie(?Typologie $typologie): self
    {
        $this->typologie = $typologie;
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
