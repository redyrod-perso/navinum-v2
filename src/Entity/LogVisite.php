<?php

namespace App\Entity;

use App\Repository\LogVisiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogVisiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'log_visite')]
class LogVisite
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(name: 'interactif_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Interactif $interactif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $interactif_libelle = null;

    #[ORM\ManyToOne(targetEntity: Visite::class)]
    #[ORM\JoinColumn(name: 'visite_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Visite $visite = null;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $connection = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $start_at;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $end_at = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultats = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $score = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;
}
