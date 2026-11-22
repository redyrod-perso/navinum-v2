<?php

namespace App\Controller\Admin;

use App\Entity\Typologie;
use App\Form\TypologieFormType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/typologies', name: 'admin_typologie_')]
#[IsGranted('ROLE_ADMIN')]
final class TypologieAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Typologie::class,
            'form' => TypologieFormType::class,
            'route' => 'admin_typologie_',
            'label' => 'Typologie',
            'labelPlural' => 'Typologies',
            'columns' => ['libelle' => 'Libellé', 'description' => 'Description'],
            'search' => ['libelle'],
            'sort' => ['libelle' => 'ASC'],
        ];
    }
}
