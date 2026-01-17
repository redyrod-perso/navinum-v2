<?php

namespace App\Entity;

use App\Repository\VisiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'visite')]
#[ORM\Index(name: 'idx_visite_connexion', columns: ['connexion_id'])]
class Visite
{
    use EntityTrait;

    public function __construct()
    {
        $this->initializeEntity();
    }
    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Visiteur $visiteur = null;

    #[ORM\ManyToOne(targetEntity: Rfid::class)]
    #[ORM\JoinColumn(name: 'navinum_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Rfid $navinum = null;

    #[ORM\ManyToOne(targetEntity: RfidGroupeVisiteur::class)]
    #[ORM\JoinColumn(name: 'groupe_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?RfidGroupeVisiteur $groupe = null;

    #[ORM\ManyToOne(targetEntity: Exposition::class)]
    #[ORM\JoinColumn(name: 'exposition_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Exposition $exposition = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $exposition_libelle = null;

    #[ORM\ManyToOne(targetEntity: Parcours::class)]
    #[ORM\JoinColumn(name: 'parcours_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Parcours $parcours = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parcours_libelle = null;

    #[ORM\ManyToOne(targetEntity: Interactif::class)]
    #[ORM\JoinColumn(name: 'interactif_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Interactif $interactif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $connexion_id = null;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    private bool $is_ending = false;

    #[ORM\Column(type: 'boolean', options: ['default' => 1])]
    private bool $is_tosync = true;
}
