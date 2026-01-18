<?php

namespace App\Entity;

use App\Repository\DeleteLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DeleteLogRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'delete_log')]
class DeleteLog
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le nom du modèle ne peut pas être vide.')]
    private ?string $modelName = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function setModelName(?string $modelName): self
    {
        $this->modelName = $modelName;
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
