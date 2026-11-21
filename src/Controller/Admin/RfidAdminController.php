<?php

namespace App\Controller\Admin;

use App\Entity\Rfid;
use App\Form\RfidType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rfid', name: 'admin_rfid_')]
#[IsGranted('ROLE_ADMIN')]
final class RfidAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Rfid::class,
            'form' => RfidType::class,
            'route' => 'admin_rfid_',
            'label' => 'Badge RFID',
            'labelPlural' => 'Badges RFID',
            'columns' => ['valeur1' => 'Valeur', 'type' => 'Type', 'groupe' => 'Groupe', 'active' => 'Actif'],
            'search' => ['valeur1', 'type'],
            'sort' => ['createdAt' => 'DESC'],
        ];
    }
}
