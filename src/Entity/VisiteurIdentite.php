<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Compte d'un visiteur chez un fournisseur OAuth (Google, Microsoft, Facebook…). Un visiteur peut en lier
 * plusieurs ; un compte fournisseur n'appartient qu'à un visiteur.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'visiteur_identite')]
#[ORM\UniqueConstraint(name: 'uniq_visiteur_identite', columns: ['fournisseur', 'sujet'])]
class VisiteurIdentite
{
    use EntityTrait;

    #[ORM\ManyToOne(targetEntity: Visiteur::class)]
    #[ORM\JoinColumn(name: 'visiteur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Visiteur $visiteur;

    #[ORM\Column(length: 32)]
    private string $fournisseur;

    /** Identifiant stable du compte chez le fournisseur. */
    #[ORM\Column(length: 255)]
    private string $sujet;

    public function __construct(Visiteur $visiteur, string $fournisseur, string $sujet)
    {
        $this->initializeEntity();
        $this->visiteur = $visiteur;
        $this->fournisseur = $fournisseur;
        $this->sujet = $sujet;
    }

    public function getVisiteur(): Visiteur
    {
        return $this->visiteur;
    }

    public function getFournisseur(): string
    {
        return $this->fournisseur;
    }

    public function getSujet(): string
    {
        return $this->sujet;
    }
}
