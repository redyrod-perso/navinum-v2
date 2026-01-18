<?php

namespace App\Entity;

use App\Repository\SyncLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SyncLogRepository::class)]
#[ORM\Table(name: 'sync_log')]
class SyncLog
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de début de synchronisation ne peut pas être null.')]
    private ?\DateTimeInterface $fromDatetimeSync = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de fin de synchronisation ne peut pas être null.')]
    private ?\DateTimeInterface $toDatetimeSync = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $isDone = false;

    public function getFromDatetimeSync(): ?\DateTimeInterface
    {
        return $this->fromDatetimeSync;
    }

    public function setFromDatetimeSync(?\DateTimeInterface $fromDatetimeSync): self
    {
        $this->fromDatetimeSync = $fromDatetimeSync;
        return $this;
    }

    public function getToDatetimeSync(): ?\DateTimeInterface
    {
        return $this->toDatetimeSync;
    }

    public function setToDatetimeSync(?\DateTimeInterface $toDatetimeSync): self
    {
        $this->toDatetimeSync = $toDatetimeSync;
        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $origin;
        return $this;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function setIsDone(bool $isDone): self
    {
        $this->isDone = $isDone;
        return $this;
    }
}
