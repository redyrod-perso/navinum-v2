<?php

namespace App\Controller\Admin;

use App\Entity\Flotte;
use App\Form\FlotteType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/flottes', name: 'admin_flotte_')]
#[IsGranted('ROLE_ADMIN')]
final class FlotteAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Flotte::class,
            'form' => FlotteType::class,
            'route' => 'admin_flotte_',
            'label' => 'Flotte',
            'labelPlural' => 'Flottes',
            'columns' => ['libelle' => 'Libellé', 'exposition' => 'Exposition', 'tosync' => 'Sync V1'],
            'search' => ['libelle'],
            'sort' => ['libelle' => 'ASC'],
        ];
    }
}
