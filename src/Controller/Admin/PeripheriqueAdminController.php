<?php

namespace App\Controller\Admin;

use App\Entity\Peripherique;
use App\Form\PeripheriqueType;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/peripheriques', name: 'admin_peripherique_')]
#[IsGranted('ROLE_ADMIN')]
final class PeripheriqueAdminController extends AbstractCrudController
{
    protected function config(): array
    {
        return [
            'entity' => Peripherique::class,
            'form' => PeripheriqueType::class,
            'route' => 'admin_peripherique_',
            'label' => 'Périphérique',
            'labelPlural' => 'Périphériques',
            'columns' => ['adresseMac' => 'Adresse MAC', 'adresseIp' => 'Adresse IP', 'serialNumber' => 'N° de série', 'flotte' => 'Flotte', 'interactif' => 'Interactif'],
            'search' => ['adresse_mac', 'adresse_ip', 'serial_number'],
            'sort' => ['createdAt' => 'DESC'],
        ];
    }
}
