<?php

namespace App\Entity;

use App\Repository\ParcoursInteractifRepository;
use App\Service\ConfigSchema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un interactif placé dans un parcours, avec sa position et SA configuration.
 *
 * Le même interactif peut figurer dans plusieurs parcours (musées) avec des réglages différents :
 * la config effective = valeurs par défaut du schéma de l'interactif, surchargées par `config`.
 */
#[ORM\Entity(repositoryClass: ParcoursInteractifRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'parcours_interactif')]
#[ORM\Index(name: 'idx_parcours_interactif_parcours', columns: ['parcours_id'])]
class ParcoursInteractif
{
    use EntityTrait;

    #[ORM\ManyToOne(targetEntity: Parcours::class, inversedBy: 'parcoursInteractifs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Parcours $parcours;

    #[ORM\ManyToOne(targetEntity: Interactif::class, inversedBy: 'parcoursInteractifs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Interactif $interactif;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $ordre = 0;

    /** Valeurs saisies pour ce parcours (surcharge des valeurs par défaut). */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    public function __construct(Parcours $parcours, Interactif $interactif)
    {
        $this->initializeEntity();
        $this->parcours = $parcours;
        $this->interactif = $interactif;
    }

    public function getParcours(): Parcours
    {
        return $this->parcours;
    }

    public function getInteractif(): Interactif
    {
        return $this->interactif;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /** Configuration injectée dans l'interactif au lancement. */
    public function getEffectiveConfig(): array
    {
        return ConfigSchema::merge($this->interactif->getDefaultConfig(), $this->config ?? []);
    }
}
