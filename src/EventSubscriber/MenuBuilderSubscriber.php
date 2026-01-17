<?php

namespace App\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuBuilderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.menu.admin.main' => 'buildMenu',
        ];
    }

    public function buildMenu(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        // Section Expositions
        $menu
            ->addChild('expositions', [
                'route' => 'admin_exposition_index',
                'label' => 'Expositions',
                'labelAttributes' => ['icon' => 'book'],
            ])
            ->setAttribute('type', 'link');

        // Section Interactifs
        $menu
            ->addChild('interactifs', [
                'route' => 'admin_interactif_index',
                'label' => 'Interactifs',
                'labelAttributes' => ['icon' => 'tablet'],
            ])
            ->setAttribute('type', 'link');

        // Section Visiteurs
        $menu
            ->addChild('visiteurs', [
                'route' => 'admin_visiteur_index',
                'label' => 'Visiteurs',
                'labelAttributes' => ['icon' => 'users'],
            ])
            ->setAttribute('type', 'link');

        // Section RFID
        $menu
            ->addChild('rfid', [
                'route' => 'admin_rfid_index',
                'label' => 'RFID',
                'labelAttributes' => ['icon' => 'barcode'],
            ])
            ->setAttribute('type', 'link');

        // Section Flottes
        $menu
            ->addChild('flottes', [
                'route' => 'admin_flotte_index',
                'label' => 'Flottes',
                'labelAttributes' => ['icon' => 'cubes'],
            ])
            ->setAttribute('type', 'link');

        // Section Périphériques
        $menu
            ->addChild('peripheriques', [
                'route' => 'admin_peripherique_index',
                'label' => 'Périphériques',
                'labelAttributes' => ['icon' => 'desktop'],
            ])
            ->setAttribute('type', 'link');
    }
}
