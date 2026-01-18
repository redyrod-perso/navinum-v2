<?php

namespace App\Entity;

use App\Repository\RulerzListenerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RulerzListenerRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'rulerz_listenner')]
class RulerzListener
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\ManyToOne(targetEntity: Rulerz::class)]
    #[ORM\JoinColumn(name: 'rulerz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le rulerz ne peut pas être null.')]
    private ?Rulerz $rulerz = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $event = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getRulerz(): ?Rulerz
    {
        return $this->rulerz;
    }

    public function setRulerz(?Rulerz $rulerz): self
    {
        $this->rulerz = $rulerz;
        return $this;
    }

    public function getEvent(): ?string
    {
        return $this->event;
    }

    public function setEvent(?string $event): self
    {
        $this->event = $event;
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
