# Architecture DTO pour API Platform

## Vue d'ensemble

Cette architecture utilise des DTOs (Data Transfer Objects) pour les opérations GET de l'API, éliminant le besoin de serialization groups. Elle suit l'approche recommandée par la documentation officielle d'API Platform.

## Principe

Les DTOs permettent de:
- Séparer le modèle interne (Entities) du contrat API public
- Exposer uniquement les champs nécessaires
- Éviter la pollution des entités avec des annotations de sérialisation
- Garantir la stabilité de l'API lors des changements du modèle de données

## Structure

```
src/
├── Dto/
│   └── RfidGroupeOutput.php          # DTO en lecture seule
└── State/
    └── Provider/
        └── RfidGroupeProvider.php    # State Provider spécifique
```

## Composants

### 1. DTO Output (readonly class)

Le DTO est une simple classe readonly qui définit le contrat public de l'API :

```php
namespace App\Dto;

use Symfony\Component\Uid\Uuid;

final readonly class RfidGroupeOutput
{
    public function __construct(
        public Uuid $id,
        public string $nom,
    ) {
    }
}
```

**Caractéristiques:**
- Classe `readonly` pour l'immutabilité
- Propriétés publiques (pas de getters nécessaires)
- Expose uniquement les champs nécessaires en lecture
- Type-safe avec types PHP stricts

### 2. State Provider

Le State Provider est responsable de récupérer les données et de les transformer en DTO :

```php
namespace App\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\RfidGroupeOutput;
use App\Entity\RfidGroupe;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RfidGroupeProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $isCollection = $operation instanceof CollectionOperationInterface;

        // Déléguer la récupération des données au provider Doctrine
        $data = $isCollection
            ? $this->collectionProvider->provide($operation, $uriVariables, $context)
            : $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$data) {
            return null;
        }

        // Transformer en DTO
        if ($isCollection) {
            $dtos = [];
            foreach ($data as $entity) {
                $dtos[] = $this->transformToDto($entity);
            }

            // Préserver la pagination
            if ($data instanceof PaginatorInterface) {
                return new TraversablePaginator(
                    new \ArrayIterator($dtos),
                    $data->getCurrentPage(),
                    $data->getItemsPerPage(),
                    $data->getTotalItems()
                );
            }

            return $dtos;
        }

        return $this->transformToDto($data);
    }

    private function transformToDto(RfidGroupe $entity): RfidGroupeOutput
    {
        return new RfidGroupeOutput(
            id: $entity->getId(),
            nom: $entity->getNom(),
        );
    }
}
```

**Points clés:**
- Décore les providers Doctrine intégrés
- Gère à la fois les items individuels et les collections
- Préserve la pagination avec `TraversablePaginator`
- Méthode de transformation simple et lisible

### 3. Configuration de l'entité

L'entité spécifie le DTO en output et le provider personnalisé :

```php
use App\Dto\RfidGroupeOutput;
use App\State\Provider\RfidGroupeProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            output: RfidGroupeOutput::class,
            provider: RfidGroupeProvider::class
        ),
        new Get(
            output: RfidGroupeOutput::class,
            provider: RfidGroupeProvider::class
        ),
        new Post(),
        new Put(),
        new Delete(),
    ],
    denormalizationContext: ['groups' => ['rfid_groupe:write']],
)]
class RfidGroupe
{
    // Pas besoin de Groups pour les GET
    #[Groups(['rfid_groupe:write'])]
    private string $nom;
}
```

**Points importants:**
- `output` spécifie le DTO pour les GET
- `provider` utilise notre State Provider personnalisé
- POST/PUT/DELETE utilisent toujours les serialization groups
- Plus besoin de `normalizationContext` pour les GET

## Avantages

### 1. Conformité API Platform
- Suit l'approche officielle recommandée
- Compatible avec toutes les versions d'API Platform
- Pas de dépendance à Symfony 7.4+ (Object Mapper)

### 2. Simplicité
- Un DTO + un Provider par ressource
- Code facile à comprendre et maintenir
- Pas d'abstraction complexe

### 3. Flexibilité
- Transformation totalement contrôlée
- Logique métier possible dans le provider
- Facile à tester

### 4. Performance
- Pagination préservée automatiquement
- Pas de surcharge de réflexion
- Transformations explicites et optimisées

## Ajouter un nouveau DTO

### 1. Créer le DTO

```php
// src/Dto/ExpositionOutput.php
namespace App\Dto;

use Symfony\Component\Uid\Uuid;

final readonly class ExpositionOutput
{
    public function __construct(
        public Uuid $id,
        public string $nom,
        public ?string $description,
    ) {
    }
}
```

### 2. Créer le State Provider

```php
// src/State/Provider/ExpositionProvider.php
namespace App\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ExpositionOutput;
use App\Entity\Exposition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ExpositionProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $isCollection = $operation instanceof CollectionOperationInterface;

        $data = $isCollection
            ? $this->collectionProvider->provide($operation, $uriVariables, $context)
            : $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$data) {
            return null;
        }

        if ($isCollection) {
            $dtos = [];
            foreach ($data as $entity) {
                $dtos[] = $this->transformToDto($entity);
            }

            if ($data instanceof PaginatorInterface) {
                return new TraversablePaginator(
                    new \ArrayIterator($dtos),
                    $data->getCurrentPage(),
                    $data->getItemsPerPage(),
                    $data->getTotalItems()
                );
            }

            return $dtos;
        }

        return $this->transformToDto($data);
    }

    private function transformToDto(Exposition $entity): ExpositionOutput
    {
        return new ExpositionOutput(
            id: $entity->getId(),
            nom: $entity->getNom(),
            description: $entity->getDescription(),
        );
    }
}
```

### 3. Configurer l'entité

```php
use App\Dto\ExpositionOutput;
use App\State\Provider\ExpositionProvider;

#[ApiResource(
    operations: [
        new GetCollection(
            output: ExpositionOutput::class,
            provider: ExpositionProvider::class
        ),
        new Get(
            output: ExpositionOutput::class,
            provider: ExpositionProvider::class
        ),
        // ... autres opérations
    ],
)]
class Exposition
{
    // ...
}
```

## Bonnes pratiques

1. **DTOs readonly**: Toujours utiliser `readonly` pour l'immutabilité
2. **Un provider par ressource**: Éviter les providers génériques complexes
3. **Transformation simple**: Garder la méthode `transformToDto()` simple et lisible
4. **GET uniquement**: N'utiliser les DTOs que pour les opérations de lecture
5. **Nommage cohérent**: `{Entity}Output` pour les DTOs, `{Entity}Provider` pour les providers

## Migration depuis serialization groups

**Avant:**
```php
#[Groups(['rfid_groupe:read'])]
private string $nom;

#[ApiResource(
    normalizationContext: ['groups' => ['rfid_groupe:read']],
)]
```

**Après:**
```php
// Plus de Groups sur les propriétés pour les GET

#[ApiResource(
    operations: [
        new Get(
            output: RfidGroupeOutput::class,
            provider: RfidGroupeProvider::class
        ),
    ],
)]
```

## Tests

Les tests doivent vérifier:
- Le `@type` correspond au nom du DTO
- Seuls les champs du DTO sont présents
- La pagination fonctionne correctement
- Les filtres fonctionnent

```php
$this->assertJsonContains([
    '@type' => 'RfidGroupeOutput',
    'id' => $id,
    'nom' => 'Test',
]);

$this->assertArrayNotHasKey('createdAt', $data);
$this->assertArrayNotHasKey('updatedAt', $data);
$this->assertArrayNotHasKey('isTosync', $data);
```

## Références

- [API Platform - DTOs](https://api-platform.com/docs/core/dto/)
- [API Platform - State Providers](https://api-platform.com/docs/core/state-providers/)
