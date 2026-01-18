<?php

namespace App\Entity;

use App\Repository\SentMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SentMessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'sent_message')]
class SentMessage
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'L\'expéditeur ne peut pas être vide.')]
    #[Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide.')]
    private ?string $mailFrom = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le destinataire ne peut pas être vide.')]
    #[Assert\Email(message: 'L\'adresse email {{ value }} n\'est pas valide.')]
    private ?string $mailTo = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le sujet ne peut pas être vide.')]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $isTosync = true;

    public function getMailFrom(): ?string
    {
        return $this->mailFrom;
    }

    public function setMailFrom(?string $mailFrom): self
    {
        $this->mailFrom = $mailFrom;
        return $this;
    }

    public function getMailTo(): ?string
    {
        return $this->mailTo;
    }

    public function setMailTo(?string $mailTo): self
    {
        $this->mailTo = $mailTo;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
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
