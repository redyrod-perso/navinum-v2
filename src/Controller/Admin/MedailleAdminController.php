<?php

namespace App\Controller\Admin;

use App\Entity\Medaille;
use App\Form\MedailleFormType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/medailles', name: 'admin_medaille_')]
#[IsGranted('ROLE_ADMIN')]
final class MedailleAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Medaille::class,
            'form' => MedailleFormType::class,
            'route' => 'admin_medaille_',
            'label' => 'Médaille',
            'labelPlural' => 'Médailles',
            'columns' => ['libelle' => 'Libellé', 'interactif' => 'Interactif', 'exposition' => 'Exposition', 'unique' => 'Unique'],
            'search' => ['libelle'],
            'sort' => ['libelle' => 'ASC'],
        ];
    }
}
