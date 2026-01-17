# navinum-speckit Development Guidelines

Auto-generated from all feature plans. Last updated: 2025-01-17

## Active Technologies

### Backend
- PHP 8.3+ avec Symfony 7
- Sylius Bundle pour administration
- Doctrine ORM avec entités existantes
- API Platform pour APIs REST
- FrankenPHP + Mercure pour performances
- Bootstrap 5 pour interface moderne

### Frontend / Interactifs
- React 18 pour les interactifs (quiz, jeux)
- Navinum API Client (@navinum/api) - Librairie JavaScript
- Webpack + Babel pour build et compatibilité
- Vanilla JS pour interfaces simples

## Project Structure

```text
# Backend Symfony
src/
├── Entity/          # Entités Doctrine (Parcours, Exposition, Interactif, Visiteur, etc.)
├── Form/            # Formulaires Sylius
├── Repository/      # Repositories avec QueryBuilders optimisés
├── Controller/      # Contrôleurs (Admin + API)
│   ├── Admin/       # Administration Sylius
│   └── SessionController.php  # API Sessions pour quiz
├── ApiResource/     # Ressources API Platform
└── EventSubscriber/ # Event subscribers

config/
├── packages/        # Configuration bundles (API Platform, Sylius, etc.)
└── routes/          # Routes (admin, API)

templates/
├── admin/           # Templates administration Sylius
└── security/        # Templates authentification

# Frontend / Interactifs
public/
├── quiz/            # Application Quiz React
│   ├── index.html
│   ├── app.jsx      # Composant principal React
│   ├── styles.css
│   └── questions/   # Fichiers de questions par thème
├── build/           # Librairies JS buildées
│   └── navinum-api.js
└── ...

# Librairie API JavaScript
assets/lib/navinum-api/
├── src/
│   ├── core/              # Composants core (ApiClient, Config, Errors)
│   ├── resources/         # APIs par ressource (Session, Exposition, etc.)
│   └── index.js           # Point d'entrée
├── dist/                  # Builds générés (dev + prod)
├── README.md              # Documentation complète
├── CHANGELOG.md           # Historique versions
├── CONTRIBUTING.md        # Guide contribution
└── package.json

tests/                # Tests PHPUnit
```

## Commands

### Symfony Backend

```bash
# Configuration et cache
bin/console cache:clear
bin/console doctrine:mapping:info

# Tests et validation
bin/console debug:config sylius_resource
bin/console debug:router                      # Toutes les routes
bin/console debug:router | grep parcours      # Routes parcours
bin/console debug:router | grep api           # Routes API

# Développement
composer require sylius/grid-bundle
bin/console sylius:theme:assets:install

# Base de données
bin/console doctrine:migrations:migrate
bin/console doctrine:schema:validate

# Serveur
make dev          # Démarrer FrankenPHP (si Makefile configuré)
# ou
frankenphp php-server
```

### JavaScript API Library

```bash
# Dans assets/lib/navinum-api/

# Installation des dépendances
npm install

# Build
npm run build          # Build dev + prod
npm run build:dev      # Build développement avec source maps
npm run build:prod     # Build production minifié
npm run watch          # Watch mode pour développement

# Documentation
npm run doc:reminder   # Afficher le rappel de documentation

# Copier le build dans public
cp dist/navinum-api.js ../../../public/build/

# Installation du git hook (recommandé)
cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

### Makefile Shortcuts (si configuré)

```bash
make sessions-reset      # Réinitialiser les sessions
make sessions-reset-api  # Réinitialiser via API
```

## Code Style

### Backend PHP/Symfony

- Entités avec EntityTrait pour UUID et timestamps
- **API Platform avec DTOs** (pas de serialization groups pour GET)
- FormType avec validation Symfony
- QueryBuilder optimisés pour éviter N+1 queries
- Templates Twig avec Bootstrap 5
- Relations many-to-many avec by_reference: false
- Controllers: une action = une responsabilité
- Services injectés via autowiring

#### Architecture DTO pour API Platform

**Principe:** Les opérations GET utilisent des DTOs (Data Transfer Objects) au lieu de serialization groups.

**Structure:**
```
src/
├── Dto/                           # DTOs output (readonly classes)
│   ├── MapperInterface.php        # Interface avec auto-tagging
│   ├── {Entity}Mapper.php         # Mapper Entité → DTO
│   └── {Entity}Output.php         # DTO readonly
└── State/Provider/
    └── EntityToDtoStateProvider.php  # Provider générique
```

**Configuration entité:**
```php
#[ApiResource(
    operations: [
        new GetCollection(
            output: RfidGroupeOutput::class,
            provider: EntityToDtoStateProvider::class
        ),
        new Get(
            output: RfidGroupeOutput::class,
            provider: EntityToDtoStateProvider::class
        ),
        new Post(),  // Pas de DTO, utilise serialization groups
        new Put(),   // Pas de DTO
    ],
    denormalizationContext: ['groups' => ['rfid_groupe:write']],
)]
```

**Avantages:**
- Séparation claire entité/DTO
- Type-safety avec readonly classes
- Autowire iterator (Symfony 7.1+) pour extensibilité
- Pas de pollution des entités avec Groups
- Performance (pas de réflexion)

**Documentation complète:** `docs/dto-architecture.md`

### Frontend JavaScript

- **Navinum API Client**: TOUJOURS utiliser la librairie pour les appels API
- React: Functional components avec hooks (useState, useEffect)
- Naming: camelCase pour variables/fonctions, PascalCase pour composants
- Async/await pour les appels asynchrones
- Try/catch pour la gestion d'erreurs avec types spécifiques

**Exemple d'utilisation de l'API:**
```javascript
// ✅ BON - Utiliser la librairie
const api = new NavinumAPI({ debug: true });
const session = await api.sessions.create({ playerName: 'Alice' });

// ❌ MAUVAIS - Appel fetch direct
const res = await fetch('/api/session/create', { ... });
```

## Documentation Guidelines

### ⚠️ RÈGLE IMPORTANTE

**À CHAQUE modification de la librairie navinum-api OU des API Symfony, la documentation DOIT être mise à jour.**

### Checklist de documentation

Avant chaque commit touchant la librairie ou les APIs:

- [ ] **JSDoc** à jour dans le code source (voir `assets/lib/navinum-api/.jsdoc-template.js`)
- [ ] **README.md** mis à jour si API publique modifiée
- [ ] **CHANGELOG.md** mis à jour avec version et changements
- [ ] Exemples testés et fonctionnels
- [ ] Build réussi (`npm run build`)

### Fichiers de documentation

| Fichier | Rôle | Quand modifier |
|---------|------|----------------|
| `assets/lib/navinum-api/README.md` | Documentation utilisateur | Modification API publique |
| `assets/lib/navinum-api/CHANGELOG.md` | Historique versions | Chaque commit significatif |
| `assets/lib/navinum-api/CONTRIBUTING.md` | Guide contribution | Changement de process |
| `assets/lib/navinum-api/.jsdoc-template.js` | Templates JSDoc | Rarement |
| `CLAUDE.md` (ce fichier) | Guidelines projet | Changements structurels |

**Voir:** `assets/lib/navinum-api/CONTRIBUTING.md` pour le guide complet

## Navinum API Client (@navinum/api)

### Architecture

La librairie JavaScript centralise tous les appels aux APIs Symfony pour les interactifs.

**Pattern utilisé:** Resource Pattern avec héritage
- Chaque ressource (Session, Exposition, etc.) = 1 classe
- BaseResource fournit CRUD standard (getAll, getById, create, update, delete)
- Classes spécifiques ajoutent méthodes métier

### Ressources disponibles

```javascript
const api = new NavinumAPI({ debug: true });

// Sessions (quiz multi-joueur)
api.sessions.create()
api.sessions.join()
api.sessions.start()
api.sessions.updateScore()
api.sessions.get()

// Expositions
api.expositions.getAll()
api.expositions.getById()
api.expositions.getActive()
api.expositions.search()

// Parcours
api.parcours.getAll()
api.parcours.getByExposition()

// Interactifs
api.interactifs.getAll()
api.interactifs.getByParcours()
api.interactifs.logInteraction()

// Visiteurs
api.visiteurs.register()
api.visiteurs.getByRfid()

// RFID
api.rfid.scan()
api.rfid.associate()

// Log Visites
api.logVisites.track()
api.logVisites.getByVisiteur()
api.logVisites.getStats()
```

### Ajouter une nouvelle ressource API

1. **Créer le fichier** `src/resources/NouvelleAPI.js`:
```javascript
import { BaseResource } from './BaseResource.js';

export class NouvelleAPI extends BaseResource {
    constructor(client) {
        super(client, 'resource_name', { basePath: '/api/nouvelle' });
    }

    // Méthodes spécifiques
    async methodSpeciale(params) {
        return this.client.get(`${this.basePath}/speciale`, { params });
    }
}
```

2. **Importer dans** `src/index.js`:
```javascript
import { NouvelleAPI } from './resources/NouvelleAPI.js';

export class NavinumAPI {
    constructor(options = {}) {
        // ...
        this.nouvelle = new NouvelleAPI(this.client);
    }
}
```

3. **Mettre à jour la documentation:**
   - ✅ Ajouter section dans README.md avec exemples
   - ✅ Ajouter entrée dans CHANGELOG.md
   - ✅ JSDoc complète sur toutes les méthodes

4. **Build et test:**
```bash
npm run build
# Tester dans un interactif
```

### Gestion des erreurs

La librairie fournit des erreurs typées:

```javascript
try {
    const session = await api.sessions.join('global', { playerName: 'Bob' });
} catch (error) {
    if (error instanceof NavinumAPI.NetworkError) {
        // Problème de connexion
        alert('Vérifiez votre connexion');
    } else if (error instanceof NavinumAPI.NotFoundError) {
        // Ressource inexistante
        console.log('Session non trouvée, création...');
        await api.sessions.create({ ... });
    } else if (error instanceof NavinumAPI.ValidationError) {
        // Données invalides
        console.error('Données invalides:', error.details);
    }
}
```

Types d'erreurs: `NetworkError`, `ValidationError`, `UnauthorizedError`, `NotFoundError`, `ServerError`

## Interactifs

### Quiz Multi-joueur

**Emplacement:** `public/quiz/`

**Technologies:** React 18 + Navinum API Client

**Fonctionnalités:**
- Lobby multi-joueurs avec polling temps réel
- Sélection de thème
- Questions à choix multiples
- Scores en temps réel
- Gestion des déconnexions

**Routes API utilisées:**
- `POST /api/session/create` - Créer session
- `POST /api/session/{id}/join` - Rejoindre
- `POST /api/session/{id}/start` - Démarrer
- `GET /api/session/{id}` - État session (polling)
- `POST /api/session/{id}/score` - Mettre à jour score
- `POST /api/session/{id}/leave` - Quitter

**Ajouter un nouveau thème:**
1. Créer `public/quiz/questions/nouveau-theme.txt`
2. Format: `Question|Réponse1|Réponse2|Réponse3|Réponse4|IndexCorrecte`
3. Ajouter dans `public/quiz/themes.json`

## Workflow de développement

### Développement backend (nouvelle API)

1. Créer l'entité Doctrine + Repository
2. Créer le Controller ou ApiResource
3. Tester avec cURL ou Postman
4. Documenter dans README si API publique

### Développement frontend (nouvel interactif)

1. Créer dossier dans `public/`
2. Inclure `<script src="/build/navinum-api.js"></script>`
3. Utiliser la librairie pour appels API
4. Tester dans le navigateur

### Modification de l'API Client

1. Modifier le code dans `assets/lib/navinum-api/src/`
2. **Mettre à jour JSDoc** (utiliser `.jsdoc-template.js`)
3. **Mettre à jour README.md** avec exemples
4. **Mettre à jour CHANGELOG.md**
5. Tester l'exemple
6. Build: `npm run build`
7. Copier dans public: `cp dist/navinum-api.js ../../../public/build/`
8. Tester dans un interactif
9. Commit avec doc à jour

### Git Hook pour rappel doc

Pour installer le hook qui rappelle de mettre à jour la doc:

```bash
cd assets/lib/navinum-api
cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

Le hook vérifie automatiquement si la documentation a été mise à jour quand du code source change.

## URLs utiles

- **Quiz:** http://localhost:8002/quiz/index.html
- **Admin:** http://localhost:8002/admin (admin/admin)
- **API Platform:** http://localhost:8002/api (si configuré)

## Troubleshooting

### Build navinum-api échoue

```bash
cd assets/lib/navinum-api
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Quiz ne se connecte pas

1. Vérifier que FrankenPHP tourne: `pgrep -f frankenphp`
2. Vérifier la console navigateur: erreurs réseau ?
3. Tester l'API directement:
```bash
curl -X POST http://localhost:8002/api/session/create \
  -H "Content-Type: application/json" \
  -d '{"playerName":"Test"}'
```

### Documentation pas à jour

1. Consulter `assets/lib/navinum-api/CONTRIBUTING.md`
2. Utiliser les templates dans `.jsdoc-template.js`
3. Lancer `npm run doc:reminder` pour la checklist

## Recent Changes

- 2025-01-17: Architecture DTO avec Autowire Iterator (Symfony 7.1+)
  - **DTOs pour les GET API** remplaçant les serialization groups
  - State Provider générique `EntityToDtoStateProvider`
  - Autowire iterator avec `#[AutowireIterator]` pour mappers auto-détectés
  - Premier DTO implémenté: `RfidGroupeOutput`
  - Support pagination avec `TraversablePaginator`
  - Documentation complète dans `docs/dto-architecture.md`
  - Tous tests API passent (14/14)

- 2025-01-17: Création de la librairie navinum-api v0.1.0
  - Core: ApiClient, Config, Errors avec retry et intercepteurs
  - Resources: Session, Exposition, Parcours, Interactif, Visiteur, RFID, LogVisite
  - Documentation complète (README, CHANGELOG, CONTRIBUTING)
  - Intégration dans le Quiz multi-joueur
  - Git hooks et templates JSDoc

- 004-admin-parcours: Administration des Parcours avec Sylius Bundle intégré

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
