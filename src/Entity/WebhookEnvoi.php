<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Journal d'un envoi de webhook (#10) : statut, tentatives, dernière réponse. */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'webhook_envoi')]
#[ORM\Index(name: 'idx_webhook_envoi_webhook', columns: ['webhook_id', 'created_at'])]
class WebhookEnvoi
{
    use EntityTrait;

    public const EN_ATTENTE = 'en_attente';
    public const SUCCES = 'succes';
    public const ECHEC = 'echec';

    #[ORM\ManyToOne(targetEntity: Webhook::class)]
    #[ORM\JoinColumn(name: 'webhook_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Webhook $webhook;

    #[ORM\Column(length: 64)]
    private string $evenement;

    #[ORM\Column(type: 'json')]
    private array $donnees;

    #[ORM\Column(length: 16)]
    private string $statut = self::EN_ATTENTE;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $tentatives = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $codeHttp = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $erreur = null;

    public function __construct(Webhook $webhook, string $evenement, array $donnees)
    {
        $this->initializeEntity();
        $this->webhook = $webhook;
        $this->evenement = $evenement;
        $this->donnees = $donnees;
    }

    public function getWebhook(): Webhook
    {
        return $this->webhook;
    }

    public function getEvenement(): string
    {
        return $this->evenement;
    }

    public function getDonnees(): array
    {
        return $this->donnees;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getTentatives(): int
    {
        return $this->tentatives;
    }

    public function getCodeHttp(): ?int
    {
        return $this->codeHttp;
    }

    public function getErreur(): ?string
    {
        return $this->erreur;
    }

    public function tentative(?int $codeHttp, ?string $erreur): void
    {
        ++$this->tentatives;
        $this->codeHttp = $codeHttp;
        $this->erreur = null === $erreur ? null : mb_substr($erreur, 0, 500);
        $this->statut = null === $erreur ? self::SUCCES : self::EN_ATTENTE;
    }

    public function echec(): void
    {
        $this->statut = self::ECHEC;
    }
}
