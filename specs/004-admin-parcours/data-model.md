# Modèle de Données : Administration des Parcours avec Sylius

**Fonctionnalité** : Administration des Parcours avec Sylius  
**Date** : 2025-12-25  
**Source** : Entités Doctrine existantes et exigences fonctionnelles

## Entités Principales

### Parcours

**Description** : Entité centrale représentant un itinéraire de visite muséal avec relations vers expositions et interactifs.

**Champs** :
- `id` : UUID (identifiant unique, EntityTrait)
- `libelle` : string(255), NOT NULL, UNIQUE (nom du parcours)
- `ordre` : integer, NULLABLE (ordre d'affichage/tri)
- `is_tosync` : boolean, DEFAULT true (marqueur synchronisation Navinum V1)
- `createdAt` : datetime_immutable (horodatage création, EntityTrait)
- `updatedAt` : datetime (horodatage modification, EntityTrait)

**Relations** :
- `expositions` : ManyToMany vers Exposition (parcours peut être associé à plusieurs expositions)
- `interactifs` : ManyToMany vers Interactif (parcours contient plusieurs interactifs ordonnés)

**Contraintes** :
- `libelle` doit être unique (contrainte BDD)
- `ordre` optionnel pour flexibilité organisationnelle
- Relations many-to-many sans propriétés additionnelles (tables de liaison simples)

**Index Recommandés** :
```sql
CREATE INDEX IDX_parcours_ordre ON parcours (ordre);
CREATE INDEX IDX_parcours_libelle ON parcours (libelle);
CREATE INDEX IDX_parcours_tosync ON parcours (is_tosync);
```

### Exposition

**Description** : Événement muséal ou thématique pouvant être associé à plusieurs parcours.

**Champs Existants** :
- `id` : UUID (EntityTrait)
- `libelle` : string (nom de l'exposition)
- `description` : text (description détaillée)
- `date_debut` : datetime (début exposition)
- `date_fin` : datetime (fin exposition)
- `is_tosync` : boolean (synchronisation V1)
- `createdAt`, `updatedAt` : timestamps (EntityTrait)

**Relations** :
- `parcours` : ManyToMany vers Parcours (exposition peut proposer plusieurs parcours)

**Utilisation dans Admin Parcours** :
- Sélection multiple dans formulaires de création/modification parcours
- Affichage dans grilles pour voir associations actives
- Filtrage possible par exposition pour organiser les parcours

### Interactif

**Description** : Contenu interactif (jeu, quiz, multimédia) intégré dans les parcours.

**Champs Existants** :
- `id` : UUID (EntityTrait)  
- `libelle` : string (nom de l'interactif)
- `description` : text (description du contenu)
- `type` : string (type d'interactif : jeu, quiz, video, etc.)
- `duree_estimee` : integer (durée en minutes)
- `is_tosync` : boolean (synchronisation V1)
- `createdAt`, `updatedAt` : timestamps (EntityTrait)

**Relations** :
- `parcours` : ManyToMany vers Parcours (interactif peut être dans plusieurs parcours)

**Utilisation dans Admin Parcours** :
- Sélection multiple dans formulaires avec possibilité d'ordonnancement
- Affichage séquentiel dans les détails de parcours
- Gestion de l'ordre d'apparition dans l'expérience visiteur

## Tables de Liaison

### exposition_parcours

**Structure** :
```sql
CREATE TABLE exposition_parcours (
    exposition_id UUID NOT NULL,
    parcours_id UUID NOT NULL,
    PRIMARY KEY (exposition_id, parcours_id),
    FOREIGN KEY (exposition_id) REFERENCES exposition(id) ON DELETE CASCADE,
    FOREIGN KEY (parcours_id) REFERENCES parcours(id) ON DELETE CASCADE
);
```

**Utilisation** :
- Association N:M entre expositions et parcours
- Suppression en cascade pour maintenir intégrité
- Pas de champs additionnels (relation simple)

### parcours_interactif

**Structure** :
```sql
CREATE TABLE parcours_interactif (
    parcours_id UUID NOT NULL,
    interactif_id UUID NOT NULL,
    PRIMARY KEY (parcours_id, interactif_id),
    FOREIGN KEY (parcours_id) REFERENCES parcours(id) ON DELETE CASCADE,
    FOREIGN KEY (interactif_id) REFERENCES interactif(id) ON DELETE CASCADE
);
```

**Utilisation** :
- Association N:M entre parcours et interactifs
- Suppression en cascade pour maintenir intégrité
- Ordre géré côté application (pas en BDD pour cette version)

## Règles de Validation

### Parcours

**Validation Métier** :
- `libelle` : obligatoire, longueur 1-255 caractères, unique
- `ordre` : optionnel, entier positif si fourni
- `is_tosync` : booléen, défaut true pour nouveau parcours
- Au moins une association (exposition OU interactif) recommandée

**Validation Technique** :
```php
// Dans ParcoursType
->add('libelle', TextType::class, [
    'constraints' => [
        new NotBlank(['message' => 'Le libellé est obligatoire']),
        new Length(['max' => 255])
    ]
])
->add('ordre', IntegerType::class, [
    'constraints' => [
        new PositiveOrZero(['message' => 'L\'ordre doit être positif'])
    ]
])
```

### Relations

**Validation Relations** :
- Expositions : validation que les expositions sélectionnées existent et sont actives
- Interactifs : validation que les interactifs sélectionnés existent et sont disponibles
- Cohérence métier : avertissement si parcours sans aucune relation

## États et Transitions

### État Parcours

**États Possibles** :
- **Brouillon** : Nouveau parcours en cours de configuration
- **Actif** : Parcours configuré et disponible pour les visiteurs  
- **Inactif** : Parcours temporairement désactivé
- **Archivé** : Parcours historique conservé pour référence

**Transitions** :
- Brouillon → Actif : validation complétude (au moins une exposition ET un interactif)
- Actif ↔ Inactif : basculement manuel par administrateur
- Actif → Archivé : parcours terminé, conservation historique

**Implémentation** :
- Champ `statut` : enum('brouillon', 'actif', 'inactif', 'archive')
- Workflow Symfony pour gérer transitions
- Validation selon état pour opérations autorisées

## Performance et Optimisation

### Requêtes Optimisées

**QueryBuilder Parcours** :
```php
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

**Bénéfices** :
- Préchargement des relations pour éviter N+1 queries
- Tri optimisé au niveau BDD
- Support pagination native Sylius Grid

### Cache et Performance

**Stratégies** :
- Cache Doctrine second level pour entités fréquemment lues
- Cache applicatif pour listes de sélection (expositions/interactifs)
- Pagination avec limite raisonnable (50 éléments par page)

## Compatibilité et Migration

### Navinum V1

**Champs Conservés** :
- `is_tosync` : maintient compatibilité synchronisation
- Structure UUID : compatible avec système existant
- Noms de champs : identiques à V1 pour migration transparente

**Points d'Attention** :
- Validation des contraintes existantes avant migration
- Script de migration pour données historiques si nécessaire
- Tests de non-régression sur API existante

### Évolutivité

**Extensions Futures** :
- Champ `ordre` dans tables de liaison pour ordonnancement précis
- Métadonnées supplémentaires (durée totale calculée, difficulté)
- Relations vers autres entités (visiteur, organisateur)
- Versioning des parcours pour historique

Cette structure maintient la compatibilité avec l'existant tout en permettant une gestion riche des parcours via l'interface Sylius.