<?php

namespace App\Controller\Admin;

use App\Entity\Visiteur;
use App\Form\VisiteurType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/visiteurs', name: 'admin_visiteur_')]
#[IsGranted('ROLE_ADMIN')]
final class VisiteurAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Visiteur::class,
            'form' => VisiteurType::class,
            'route' => 'admin_visiteur_',
            'label' => 'Visiteur',
            'labelPlural' => 'Visiteurs',
            'columns' => ['pseudoSon' => 'Pseudo', 'prenom' => 'Prénom', 'nom' => 'Nom', 'email' => 'Email', 'active' => 'Actif'],
            'search' => ['nom', 'prenom', 'email', 'pseudo_son'],
            'sort' => ['createdAt' => 'DESC'],
        ];
    }
}
