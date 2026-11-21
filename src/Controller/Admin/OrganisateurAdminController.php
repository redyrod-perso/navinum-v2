<?php

namespace App\Controller\Admin;

use App\Entity\Organisateur;
use App\Form\OrganisateurType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organisateurs', name: 'admin_organisateur_')]
#[IsGranted('ROLE_ADMIN')]
final class OrganisateurAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Organisateur::class,
            'form' => OrganisateurType::class,
            'route' => 'admin_organisateur_',
            'label' => 'Organisateur',
            'labelPlural' => 'Organisateurs',
            'columns' => ['libelle' => 'Libellé', 'tosync' => 'Sync V1'],
            'search' => ['libelle'],
            'sort' => ['libelle' => 'ASC'],
        ];
    }
}
