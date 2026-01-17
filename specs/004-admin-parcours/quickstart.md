# Guide de Démarrage Rapide : Administration des Parcours avec Sylius

**Fonctionnalité** : Administration des Parcours avec Sylius  
**Date** : 2025-12-25  
**Prérequis** : Symfony 7 + Sylius Bundle installé

## Vue d'Ensemble

Ce guide permet de déployer rapidement l'interface d'administration des parcours en utilisant les composants Sylius sur l'application Navinum existante.

## Installation et Configuration

### 1. Vérification des Prérequis

```bash
# Vérifier que Sylius Bundle est installé
composer show | grep sylius

# Résultat attendu :
# sylius/grid-bundle
# sylius/resource-bundle  
# sylius/ui-bundle
```

### 2. Configuration Sylius Resource

Créer le fichier `config/packages/sylius_resource.yaml` :

```yaml
sylius_resource:
    resources:
        app.parcours:
            driver: doctrine/orm
            classes:
                model: App\Entity\Parcours
                repository: App\Repository\ParcoursRepository
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                factory: Sylius\Component\Resource\Factory\Factory
                form: App\Form\ParcoursType
            validation_groups:
                default: [sylius]
```

### 3. Configuration Sylius Grid

Ajouter dans `config/packages/sylius_grid.yaml` :

```yaml
sylius_grid:
    grids:
        app_admin_parcours:
            driver:
                name: doctrine/orm
                options:
                    class: App\Entity\Parcours
                    repository:
                        method: createParcoursGridQueryBuilder
            sorting:
                ordre: asc
            fields:
                libelle:
                    type: string
                    label: Libellé
                    options:
                        sortable: true
                ordre:
                    type: string
                    label: Ordre
                    options:
                        sortable: true
                expositions:
                    type: twig
                    label: Expositions
                    options:
                        template: '@SyliusGrid/Field/collection_relation.html.twig'
                        vars:
                            property: libelle
                            separator: ', '
                interactifs:
                    type: twig
                    label: Interactifs
                    options:
                        template: '@SyliusGrid/Field/collection_relation.html.twig'
                        vars:
                            property: libelle
                            separator: ', '
                is_tosync:
                    type: twig
                    label: À synchroniser
                    options:
                        template: '@SyliusUi/Grid/Field/enabled.html.twig'
                createdAt:
                    type: datetime
                    label: Créé le
                    options:
                        format: 'd/m/Y H:i'
                        sortable: true
            filters:
                libelle:
                    type: string
                    label: Rechercher par nom
                is_tosync:
                    type: boolean
                    label: À synchroniser
            actions:
                main:
                    create:
                        type: create
                        label: Nouveau Parcours
                        options:
                            icon: plus
                item:
                    show:
                        type: show
                        options:
                            icon: eye
                    update:
                        type: update
                        options:
                            icon: pencil
                    delete:
                        type: delete
                        options:
                            icon: trash
```

## Implémentation des Composants

### 1. Formulaire ParcoursType

Créer `src/Form/ParcoursType.php` :

```php
<?php

namespace App\Form;

use App\Entity\Exposition;
use App\Entity\Interactif;
use App\Entity\Parcours;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ParcoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom du parcours'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le libellé est obligatoire']),
                    new Length(['max' => 255, 'maxMessage' => 'Le libellé ne peut dépasser 255 caractères'])
                ]
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Position dans la liste (optionnel)',
                    'min' => 0
                ],
                'constraints' => [
                    new PositiveOrZero(['message' => 'L\'ordre doit être un nombre positif'])
                ]
            ])
            ->add('expositions', EntityType::class, [
                'class' => Exposition::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Expositions',
                'required' => false,
                'attr' => [
                    'class' => 'form-control select2',
                    'data-placeholder' => 'Sélectionner des expositions'
                ],
                'by_reference' => false,
                'help' => 'Sélectionnez les expositions où ce parcours sera proposé'
            ])
            ->add('interactifs', EntityType::class, [
                'class' => Interactif::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => false,
                'label' => 'Interactifs',
                'required' => false,
                'attr' => [
                    'class' => 'form-control select2',
                    'data-placeholder' => 'Sélectionner des interactifs'
                ],
                'by_reference' => false,
                'help' => 'Choisissez les contenus interactifs inclus dans ce parcours'
            ])
            ->add('is_tosync', CheckboxType::class, [
                'label' => 'À synchroniser avec Navinum V1',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Cochez pour inclure dans la synchronisation avec l\'ancien système'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'validation_groups' => ['sylius']
        ]);
    }
}
```

### 2. Optimisation Repository

Ajouter dans `src/Repository/ParcoursRepository.php` :

```php
/**
 * QueryBuilder optimisé pour la grille d'administration
 */
public function createParcoursGridQueryBuilder(): QueryBuilder
{
    return $this->createQueryBuilder('p')
        ->leftJoin('p.expositions', 'e')
        ->leftJoin('p.interactifs', 'i')
        ->addSelect('e', 'i')
        ->orderBy('p.ordre', 'ASC')
        ->addOrderBy('p.libelle', 'ASC');
}
```

### 3. Configuration des Routes

Créer `config/routes/admin_parcours.yaml` :

```yaml
app_admin_parcours:
    resource: |
        alias: app.parcours
        section: admin
        templates: "@SyliusAdmin\\Crud"
        redirect: update
        grid: app_admin_parcours
        permission: true
        vars:
            all:
                subheader: app.ui.manage_parcours
                header: app.ui.parcours
            index:
                icon: 'list-ul'
    type: sylius.resource
    prefix: /admin/parcours
```

## Templates Personnalisés

### 1. Layout Principal

Créer `templates/admin/parcours/_layout.html.twig` :

```twig
{% extends '@SyliusUi/Layout/sidebar.html.twig' %}

{% block title %}Administration des Parcours{% endblock %}

{% block sidebar %}
    {{ parent() }}
    <div class="item">
        <div class="header">
            <i class="list ul icon"></i>
            Gestion des Parcours
        </div>
        <div class="menu">
            <a class="item" href="{{ path('app_admin_parcours_index') }}">
                <i class="list icon"></i> Tous les Parcours
            </a>
            <a class="item" href="{{ path('app_admin_parcours_create') }}">
                <i class="plus icon"></i> Nouveau Parcours
            </a>
        </div>
    </div>
{% endblock %}
```

### 2. Grille Personnalisée

Créer `templates/admin/parcours/index.html.twig` :

```twig
{% extends 'admin/parcours/_layout.html.twig' %}

{% block content %}
<div class="ui stackable grid">
    <div class="sixteen wide column">
        <div class="ui segment">
            <h1 class="ui dividing header">
                <i class="list ul icon"></i>
                Gestion des Parcours
                <div class="sub header">{{ grid.data|length }} parcours trouvés</div>
            </h1>
            
            <div class="ui top attached tabular menu">
                <a class="active item">
                    <i class="list icon"></i>
                    Liste des Parcours
                </a>
            </div>
            
            <div class="ui bottom attached segment">
                {{ sylius_grid_render(grid, '@SyliusAdmin/Grid') }}
            </div>
        </div>
    </div>
</div>
{% endblock %}
```

## Tests de Validation

### 1. Test de Configuration

```bash
# Vérifier la configuration Sylius
bin/console debug:config sylius_resource

# Vérifier les routes générées
bin/console debug:router | grep parcours

# Tester la grille
bin/console cache:clear
```

### 2. Test Fonctionnel

```bash
# Accéder à l'interface d'administration
curl -X GET "https://navinum.local/admin/parcours"

# Créer un parcours de test
curl -X POST "https://navinum.local/admin/parcours" \
  -H "Content-Type: application/json" \
  -d '{
    "libelle": "Parcours Test",
    "ordre": 1,
    "is_tosync": true
  }'
```

## Fonctionnalités Disponibles

### Interface Utilisateur

- **Liste paginée** des parcours avec tri et filtres
- **Création rapide** via formulaire optimisé
- **Modification en place** des propriétés
- **Gestion des relations** many-to-many via select multiple
- **Suppression sécurisée** avec confirmation

### Fonctionnalités Métier

- **Validation automatique** unicité libellé
- **Gestion des associations** expositions et interactifs
- **Synchronisation V1** via flag is_tosync
- **Horodatage automatique** création/modification
- **Recherche avancée** par nom et statut

### Performance

- **Requêtes optimisées** avec préchargement des relations
- **Pagination native** Sylius Grid
- **Cache Doctrine** pour améliorer les performances
- **Interface responsive** compatible mobile

## Dépannage Courant

### Problèmes de Configuration

```bash
# Erreur de mapping Doctrine
bin/console doctrine:mapping:info

# Problème de cache Sylius
bin/console cache:clear --env=prod
bin/console sylius:theme:assets:install
```

### Problèmes de Permissions

```yaml
# Dans security.yaml si nécessaire
access_control:
    - { path: ^/admin/parcours, roles: ROLE_ADMIN }
```

Cette configuration permet un démarrage rapide de l'administration des parcours avec toutes les fonctionnalités essentielles opérationnelles.