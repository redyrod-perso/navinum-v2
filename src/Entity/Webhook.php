<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Webhook sortant (#10) : Navinum envoie ses événements métier (médaille obtenue, partie terminée, visiteur
 * inscrit…) à une adresse (n8n…), en asynchrone, signés en HMAC-SHA256 avec le secret du webhook.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'webhook')]
class Webhook
{
    use EntityTrait;

    /** Événements émis par Navinum (voir App\Webhook\Webhooks). */
    public const EVENEMENTS = [
        'medaille.obtenue' => 'Médaille obtenue',
        'partie.terminee' => 'Partie terminée',
        'visiteur.inscrit' => 'Visiteur inscrit (compte créé)',
    ];

    #[ORM\Column(length: 120)]
    private string $libelle;

    #[ORM\Column(length: 2048)]
    private string $url;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $evenements = [];

    /** Secret de signature, communiqué au destinataire pour vérifier les envois. */
    #[ORM\Column(length: 80)]
    private string $secret;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    /** @param list<string> $evenements */
    public function __construct(string $libelle, string $url, array $evenements, string $secret)
    {
        $this->initializeEntity();
        $this->libelle = $libelle;
        $this->url = $url;
        $this->evenements = array_values(array_intersect(array_keys(self::EVENEMENTS), $evenements));
        $this->secret = $secret;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /** @return list<string> */
    public function getEvenements(): array
    {
        return $this->evenements;
    }

    public function ecoute(string $evenement): bool
    {
        return $this->actif && \in_array($evenement, $this->evenements, true);
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }
}
