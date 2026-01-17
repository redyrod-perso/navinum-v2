# Recherche Technique : Administration des Parcours avec Sylius

**Fonctionnalité** : Administration des Parcours avec Sylius  
**Date** : 2025-12-25  
**Contexte** : Évolution de l'admin existante pour la gestion CRUD des parcours

## Décisions Techniques Prises

### 1. Intégration Sylius Resource Bundle

**Décision** : Utiliser Sylius Resource Bundle pour automatiser les opérations CRUD des entités Parcours

**Rationnels** :
- L'application dispose déjà de Sylius Grid Bundle configuré
- Réduction significative du code boilerplate (contrôleurs, repositories, factories)
- Cohérence avec l'architecture Sylius déjà en place dans l'application
- Configuration déclarative vs code impératif pour les opérations CRUD

**Alternatives Considérées** :
- **Contrôleurs manuels** : Plus de contrôle mais maintenance accrue et incohérence avec l'existant
- **API Platform seul** : Nécessiterait une interface frontend séparée, complexité supplémentaire
- **Sylius Grid uniquement** : Limité pour les opérations de création/modification

### 2. Gestion des Relations Many-to-Many

**Décision** : Utiliser EntityType avec configuration optimisée pour les relations multiples

**Rationnels** :
- Sylius Grid gère nativement l'affichage des collections avec templates prêts
- EntityType avec `by_reference: false` assure la persistence correcte des relations
- Interface utilisateur intuitive avec select multiple
- Performance optimisée avec QueryBuilder personnalisé pour éviter N+1 queries

**Alternatives Considérées** :
- **CollectionType** : Plus complexe à configurer, pas nécessaire pour les relations existantes
- **Widgets JavaScript custom** : Développement supplémentaire, maintenance accrue

### 3. Migration Interface Bootstrap 5

**Décision** : Migration progressive de Semantic UI vers Bootstrap 5 pour cohérence moderne

**Rationnels** :
- Compatibilité avec Sylius 2.0 et écosystème moderne
- Maintien de la cohérence visuelle avec les standards actuels
- Templates Sylius utilisent déjà Bootstrap comme base
- Icons Bootstrap offrent une meilleure accessibilité

**Alternatives Considérées** :
- **Maintenir Semantic UI** : Technologie en fin de vie, incompatible long terme
- **Framework CSS custom** : Développement supplémentaire, maintenance accrue

### 4. Optimisation Performance

**Décision** : QueryBuilder personnalisé avec préchargement des relations

**Rationnels** :
- Évite le problème N+1 queries lors de l'affichage des grilles
- Meilleure performance pour l'interface d'administration
- Tri et filtrage optimisés au niveau base de données

**Alternatives Considérées** :
- **Lazy loading par défaut** : Performance dégradée avec les collections
- **Cache de requête** : Complexité supplémentaire, pas nécessaire pour l'admin

## Configuration Technique Retenue

### Structure de Configuration Sylius

```yaml
# Configuration Resource Bundle
sylius_resource:
    resources:
        app.parcours:
            driver: doctrine/orm
            classes:
                model: App\Entity\Parcours
                repository: App\Repository\ParcoursRepository
                controller: Sylius\Bundle\ResourceBundle\Controller\ResourceController
                form: App\Form\ParcoursType

# Configuration Grid avec relations
app_admin_parcours:
    driver:
        name: doctrine/orm
        options:
            class: App\Entity\Parcours
            repository:
                method: createParcoursGridQueryBuilder
```

### Architecture Formulaires

- **FormType personnalisé** avec EntityType pour relations many-to-many
- **Validation Symfony** au niveau entité pour intégrité référentielle  
- **Configuration by_reference: false** pour persistence collections

### Templates et Interface

- **Extension @SyliusUi/layout/base.html.twig** pour cohérence
- **Bootstrap 5** avec icônes Bootstrap Icons
- **Composants réutilisables** pour les actions CRUD standard

## Impact Architecture Existante

### Modifications Minimales Requises

- **Entités** : Aucune modification, compatibilité totale maintenue
- **Configuration** : Ajout des fichiers de configuration Sylius
- **Routes** : Nouveaux endpoints admin via Sylius Resource routing
- **Templates** : Nouveaux templates Bootstrap 5, existant préservé

### Compatibilité Assurée

- **Schéma BDD** : Aucune modification requise
- **API Platform** : Maintenu pour compatibilité existante
- **Champ is_tosync** : Préservé pour synchronisation V1
- **UUID identifiants** : Compatible avec Sylius Resource Bundle

## Performance et Sécurité

### Optimisations Performance

- **Requêtes optimisées** avec JOIN pour préchargement
- **Pagination** native Sylius pour grandes collections
- **Index BDD** recommandés sur colonnes de recherche fréquente

### Mesures Sécurité

- **Symfony Security** maintenu pour contrôle d'accès
- **Validation entité** pour intégrité des relations
- **CSRF tokens** automatiques via FormType Symfony

## Conclusion

Cette approche technique permet une intégration harmonieuse de Sylius dans l'architecture existante, en minimisant les modifications tout en apportant une interface d'administration moderne et performante. La compatibilité avec Navinum V1 est assurée et l'évolutivité future est préservée.