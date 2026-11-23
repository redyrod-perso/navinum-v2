# navinum-speckit Development Guidelines

Auto-generated from all feature plans. Last updated: 2025-01-17

## Active Technologies

### Backend
- PHP 8.3+ avec Symfony 7
- Admin en Symfony pur (contrôleurs CRUD génériques + Twig Bootstrap 5) — Sylius et EasyAdmin ont été abandonnés
- Doctrine ORM avec entités existantes
- API Platform pour APIs REST
- FrankenPHP en **mode worker** + **hub Mercure intégré** (temps réel) + Vulcain
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
├── Form/            # Formulaires Symfony
├── Repository/      # Repositories avec QueryBuilders optimisés
├── Controller/      # Contrôleurs (Admin + API)
│   ├── Admin/       # Administration (AbstractCrudController + un contrôleur par section)
│   └── SessionController.php  # API Sessions pour quiz
├── ApiResource/     # Ressources API Platform
└── EventSubscriber/ # Event subscribers

config/
├── packages/        # Configuration bundles (API Platform, Mercure, security…)
└── routes/          # Routes (admin, API)

templates/
├── admin/           # Templates administration (layout, crud/ générique, parcours/, interactif/)
└── security/        # Templates authentification

# Frontend / Interactifs
public/
├── quiz/            # Application Quiz React
│   ├── index.html
│   ├── app.jsx      # Composant principal React
│   ├── styles.css
│   └── navinum.json # Manifeste : métadonnées + JSON Schema de configuration (thèmes, questions…)
├── build/           # Librairies JS buildées
│   └── navinum-api.js
└── ...

# Librairie API JavaScript
assets/lib/navinum-api/
├── src/
│   ├── core/              # Socle (ApiClient, Config, Errors, Realtime)
│   ├── resources/         # Appels HTTP par ressource du serveur (Session, Visiteur…), sans logique
│   ├── modules/           # Modules réutilisables sans HTML : erreurs/, auth/, gamification/, multijoueur/
│   ├── react/             # Hooks React facultatifs (NavinumAPI.creerHooksReact(React))
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
bin/console debug:router                      # Toutes les routes
bin/console debug:router | grep parcours      # Routes parcours
bin/console debug:router | grep api           # Routes API

# Comptes et interactifs
bin/console app:user:create admin@exemple.fr --admin            # compte administrateur
bin/console app:user:create musee@exemple.fr --organisateur="Musée X"
bin/console app:interactif:import public/quiz/navinum.json       # enregistre/maj un interactif depuis son manifeste

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

# Tests (runner natif de Node, sur le bundle construit)
npm run build:dev && npm test

# Copier le build dans public
cp dist/navinum-api.js ../../../public/build/

# Installation du git hook (recommandé)
cp .git-hooks/pre-commit-doc-reminder.sh .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

### Makefile Shortcuts (si configuré)

```bash
make sessions-reset      # Réinitialiser les sessions
```

## Code Style

### Règles de qualité (non négociables)

- **Un bug = un nouveau test** : tout bug corrigé s'accompagne d'un test qui échoue sans le correctif.
- Toute nouvelle API ou règle métier arrive avec ses tests (unitaires et/ou fonctionnels).
- Lancer les tests sur la base **dédiée** `navinum_test` : dans le conteneur, `DATABASE_URL` l'emporte sur `.env.test`
  (`docker exec -e APP_ENV=test -e DATABASE_URL=…/navinum_test navinum_app php bin/phpunit`).
- Sécurité par défaut : une ressource API est fermée (`ROLE_ADMIN`) tant qu'on n'a pas décidé de l'ouvrir.
- La suite PHPUnit doit sortir en **code 0** : `phpunit.dist.xml` échoue sur toute dépréciation, notice ou avertissement.

### Intégration continue (GitHub Actions)

`.github/workflows/ci.yml`, à chaque push sur `main` et chaque pull request (ne fusionner une PR que si tout est vert) :
- **Symfony** (PHP 8.4 + PostgreSQL 16) : `composer install`, lint YAML/Twig/conteneur, migrations depuis une base vide,
  `doctrine:schema:validate`, PHPUnit.
- **Librairie JS et quiz** (Node 22) : `npm ci`, **tests de la librairie** (`npm test`), build, vérification que
  `public/build/navinum-api.js` correspond au build (sinon : `npm run build` puis copier `dist/navinum-api.min.js`),
  syntaxe JSX du quiz, manifestes JSON valides, **aucun appel direct dans les interactifs** (`bin/check-interactifs.sh`).
- Un clone neuf doit suffire : toute variable d'environnement utilisée doit avoir une valeur par défaut dans `.env`.


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

- **Navinum API Client** : la librairie `@navinum/api` est le **seul point d'accès d'un interactif à Navinum**
  (API REST, temps réel Mercure, identification, lancement, manifeste). C'est une librairie indépendante, partagée
  avec les développeurs tiers, sur le même modèle que le futur module d'authentification.
  **Interdit dans un interactif** : `fetch`, `new EventSource`, `XMLHttpRequest`, `localStorage`/`sessionStorage`
  directs. Vérifié par `bin/check-interactifs.sh` (CI) ; un besoin manquant → l'ajouter à la librairie (avec tests).
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

// Groupes de visiteurs (quiz : identifiant de session commun)
api.rfidGroupes.list()
api.rfidGroupes.findOrCreate()
api.rfidGroupes.onCreated()          // temps réel (Mercure)

// Sessions en temps réel
api.sessions.subscribe()             // Mercure, relecture de l'état à la connexion

// Identification du joueur
api.visiteurs.identifier()           // visiteur conservé sur l'appareil ou nouvel invité
api.visiteurs.courant()
api.visiteurs.oublier()

// Lancement et manifeste
api.launch()                         // ctx.track(), ctx.identify(), ctx.onConfigChange()
api.interactifs.chargerManifeste()   // { manifest, defaults }

// Modules réutilisables (sans HTML ni design ; points d'entrée @navinum/api/erreurs|auth|gamification)
api.gamification.evenement(type, payload)  // gains { xp, medailles, totalXp } ou null, jamais bloquant
api.gamification.onGains(cb) / demanderMedaille() / bilan()
api.multijoueur.partie({ objectif })      // rejoindre(), demarrer(), marquerPoint(), terminer(), quitter(), onChange()
NavinumAPI.creerHooksReact(React)         // usePartie, useJoueur, useGains (hooks facultatifs)
api.auth.connecter({ pseudo })       // joueur + jeton visiteur, partie lancée rattachée automatiquement
api.auth.joueur() / deconnecter()
NavinumAPI.erreurs.decrire(err)      // { code, titre, message, reessayable, action, … } — aucun affichage
api.onError(info => …)               // toutes les erreurs d'API (journal)
api.sessions.quitterEnPartant()      // quitte le groupe à la fermeture de la page

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

Types d'erreurs: `NetworkError`, `ValidationError`, `UnauthorizedError`, `NotFoundError`, `GoneError`, `ServerError`

**Recommandé dans les interactifs : le module d'erreurs** (`NavinumAPI.erreurs.decrire(err)`), qui rend un **code
stable** (`RESEAU`, `LIEN_EXPIRE`, `PARTIE_EN_COURS`, `GROUPE_COMPLET`…), un titre et un message en français et
l'action conseillée. Côté Symfony, toute réponse d'erreur d'API destinée aux interactifs porte `{ "error": "…",
"code": "…" }` avec un code du catalogue (`assets/lib/navinum-api/src/modules/Erreurs.js`).

## Interactifs

### Quiz Multi-joueur

**Emplacement:** `public/quiz/`

**Technologies:** React 18 + Navinum API Client

**Fonctionnalités:** (logique dans les modules de la librairie — `api.multijoueur`, `api.auth`, `api.gamification` ;
`app.jsx` ne contient que l'affichage et les questions : c'est l'exemple de référence pour un nouvel interactif)
- Lobby multi-joueurs en temps réel (Mercure)
- Sélection de thème
- Questions à choix multiples
- Scores en temps réel
- Gestion des déconnexions

**Routes API utilisées:**
- `POST /api/session/create` - Créer session
- `POST /api/session/{id}/join` - Rejoindre
- `POST /api/session/{id}/start` - Démarrer
- `GET /api/session/{id}` - État session (lecture initiale)
- `POST /api/session/{id}/score` - Mettre à jour score (refusé si non plausible : hors partie, +1 au plus par appel → `SCORE_REFUSE`)
- `POST /api/session/{id}/leave` - Quitter (aussi à la fermeture de la page : `api.sessions.quitterEnPartant()`)
- `POST /api/session/{id}/reset`, `POST /api/sessions/clear` - maintenance, **réservées à `ROLE_ADMIN`**

Nombre maximum de joueurs **imposé par le serveur** : `create`/`join` reçoivent `launchToken` (jeton `?nv=`), la
session garde `maxPlayers` = `joueursMax` de la configuration du lancement (8 sans jeton, 50 au plus) ; au-delà, 409
`GROUPE_COMPLET`.

Les sessions sont des fichiers de `var/cache/sessions/` nommés par un hachage du nom du groupe (tout nom saisi reste
un simple fichier de ce dossier, 100 caractères max).

**Temps réel :** chaque changement de session est publié sur le hub Mercure intégré, topic
`/navinum/sessions/{id}` (`{"type": "session", "session": {…}}`). Le quiz s'y abonne avec `EventSource` et relit l'état
une fois à la connexion. Il n'y a plus de flux SSE tenu par PHP (`/api/session/{id}/stream` supprimé) : aucun thread
PHP n'est bloqué pendant une partie. Les fichiers `public/quiz/*`, `public/build/*` et `/config.js` sont servis avec
`Cache-Control: no-cache` pour que les navigateurs prennent la nouvelle version après un déploiement.

**Configuration (thèmes, questions, temps, joueurs) :** déclarée dans `public/quiz/navinum.json` (`configSchema`,
valeurs `default`) et modifiable par parcours dans l'admin (Parcours → Interactifs → Configurer). Plus de fichiers `.txt`.

### Interactifs configurables (spec `specs/005-interactifs-config`)

**Principe :** l'interactif décrit lui-même ses réglages. Aucun formulaire spécifique par interactif dans Navinum.

- `Interactif.variable` = **JSON Schema** (les `default` = config initiale). Outils : `App\Service\ConfigSchema`
  (defaults, merge, validate).
- `Exposition → Parcours → ParcoursInteractif (ordre, config JSON) → Interactif`. Config effective = défauts ⊕ config du parcours.
- Admin : formulaire **généré** depuis le schéma (json-editor), validé aussi côté serveur.
- Lancement : `<url>?nv=<jeton>` (`LaunchToken`, 12 h) → `GET /api/launch/{jeton}` (contexte + config),
  `POST /api/launch/{jeton}/events` (→ `LogVisite`, base de la gamification). Côté JS : `await api.launch()`.
- Temps réel (Mercure) : topic `/navinum/parcours-interactif/{id}/config` (config à chaud),
  `/navinum/parcours/{id}/events` (fil en direct de l'admin).
- Nouvel interactif = un manifeste `navinum.json` importé (admin → Interactifs → Importer, ou `app:interactif:import`).
- Contrat complet : `specs/005-interactifs-config/contracts/launch-api.md`.

### Visiteurs et identification (prototype)

- `POST /api/visiteur/invite` `{pseudo}` → visiteur invité + **jeton visiteur** `nvv2.<données>.<signature>` (HMAC
  avec `APP_SECRET`, sans stockage) + **secret d'appareil** `nva1.…` (empreinte en base : `VisiteurAppareil`).
  `GET /api/visiteur/moi` (Bearer) → profil.
- **Sessions (#6)** : jeton valable **30 min d'inactivité**, **12 h au plus** ; `POST /api/visiteur/renouveler` (Bearer)
  le prolonge ; `POST /api/visiteur/reprendre` `{pseudo, appareil}` rend le **même profil** sur le même appareil
  (ou `{pseudo, ancienJeton}` pour échanger un ancien `nvv1`) ; `POST /api/visiteur/deconnexion` révoque l'appareil ;
  admin › Visiteurs › « Déconnecter partout » révoque tous ses appareils. Changement de `APP_SECRET` : mettre l'ancien
  dans `VISITEUR_SECRET_PRECEDENT` (facultative) au moins 30 min.
- **Borne partagée** : `modeBorne` dans la configuration du placement (quiz : déconnexion en fin de partie, rien de
  conservé sur l'appareil). Un nouveau joueur reprend le lancement si le précédent n'est plus actif, et **toujours** en
  mode borne (le joueur parti sans se déconnecter ne reçoit pas les gains des suivants).
- `POST /api/launch/{jeton}/visiteur` (Bearer) rattache le visiteur au lancement : ses événements alimentent son `LogVisite`.
- Côté JS (lib 0.6.0) : **module `api.auth`** — `api.auth.connecter({ pseudo })` (mode invité ; `compte`, `oauth`,
  `rfid` à venir derrière la même API) rattache aussi la partie lancée. En dessous : `api.visiteurs.identifier(pseudo)` (visiteur conservé sur l'appareil, clé `navinum.visiteur`,
  ou nouvel invité), `courant()`, `oublier()`, puis `ctx.identify(token)`. Le quiz ne touche jamais au stockage lui-même.
- Le futur **module d'authentification JS** (compte en ligne, **badge RFID** en réseau local) délivrera le même jeton :
  les interactifs n'auront rien à changer. Profil public uniquement (`id`, `pseudo`, `anonyme`), jamais d'email.

### Sécurité de l'API

- Pare-feu `api` (`^/api`) **sans session** ; les futurs modes d'authentification (clé d'intégration n8n, jeton visiteur,
  RFID) s'y brancheront.
- API Platform : catalogue en lecture publique (expositions, interactifs, organisateurs) ; visiteurs, badges RFID,
  flottes, périphériques réservés à `ROLE_ADMIN` ; toute écriture réservée à `ROLE_ADMIN`. Groupes RFID : lecture et
  création publiques (quiz), modification/suppression admin.
- Ne jamais exposer une relation qui boucle (ex. `Exposition.parcours` est `#[Ignore]`).

### Rôles de l'admin

- `ROLE_ADMIN` : tout. `ROLE_ORGANISATEUR` : expositions de son organisateur (éditeur ou diffuseur) et leurs parcours.
- Comptes en base (`admin_user`) ; plus d'utilisateur en mémoire.

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
- **Admin:** http://localhost:8002/admin (compte créé avec `bin/console app:user:create`)
- **API Platform:** http://localhost:8002/api (si configuré)

## Environnements : même stack en local et sur le VPS

**Règle : ce qui démarre en local doit démarrer sur le VPS, et inversement.**

- Même Docker : **docker-ce officiel** (paquet apt) en local et sur le VPS. Pas le snap (AppArmor l'empêche d'arrêter
  ses conteneurs et casse le réseau entre conteneurs).
- Même `docker-compose.yaml` ; seules différences dans les overrides :
  - local : `docker compose -f docker-compose.yaml -f docker-compose.dev.yaml up -d` (`FRANKENPHP_WORKER_WATCH=watch`)
  - VPS : `docker compose -f docker-compose.yaml -f docker-compose.prod.yml up -d` (Traefik, `APP_ENV=prod`, `APP_DEBUG=0`)
- Un seul `Caddyfile` (worker FrankenPHP + Mercure + Vulcain), paramétré par variables d'environnement.
- Mercure est intégré à FrankenPHP : plus de conteneur `mercure` ; `MERCURE_URL` interne = `http://localhost/.well-known/mercure`.
- `MERCURE_PUBLIC_URL` = `/.well-known/mercure` (relatif, même domaine que le site, identique partout) ; l'API de lancement
  le rend absolu pour les interactifs hébergés ailleurs.
- `MERCURE_JWT_SECRET` : **32 caractères minimum** (sinon la publication échoue : « Key provided is shorter than 256 bits »).
  Docker Compose lit les `${VAR}` dans le fichier **`.env`** du dossier (pas dans `.env.local`) : en prod, mettre le vrai
  secret dans le `.env` du VPS, jamais dans le dépôt.
- Worker FrankenPHP : l'application reste en mémoire entre les requêtes → services sans état, pas de données
  propres à une requête dans des propriétés de service.

## Feuille de route et décisions de conception

Idées et décisions validées avec le porteur du projet (à transformer en tickets GitHub).

### Authentification (visiteurs et interactifs)

- **Module JS d'authentification partagé**, intégrable par n'importe quel interactif et distribué aux développeurs tiers.
- Modes : compte en ligne (email + mot de passe), **OAuth** (Google et autres fournisseurs), **badge RFID** en réseau
  local et sur le matériel équipé.
- Tous délivrent le même **jeton Bearer** visiteur (format actuel `nvv2.…` : expiration, révocation et
  rafraîchissement — faits en #6). Le pare-feu `api` (sans session) recevra l'authentificateur correspondant.
- Actuel : visiteur invité par pseudo, session de 30 min (12 h au plus), profil repris sur le même appareil (#6).

### Gamification (XP, médailles) — implémentée (#7)

- **Règles en JSON dans `Interactif.gamification`**, éditées dans l'admin (Interactifs › 🏆, formulaire généré depuis
  `App\Gamification\RulesSchema`, validation serveur de la structure et de la syntaxe des expressions) :
  ```json
  { "xp": [{ "evenement": "quiz.completed", "points": "score * 10", "typologie": "Joueur", "si": "victoire" }],
    "medailles": [{ "medaille": "Sans faute", "evenement": "quiz.completed", "si": "score == total" }],
    "attribuables": ["Explorateur"] }
  ```
- `evenement` exact ou préfixe (`quiz.*`) ; `points` et `si` sont des expressions sur les données de l'événement,
  évaluées par `SafeExpressionLanguage` (ExpressionLanguage **sans** `constant()`/`enum()`, seules `min`, `max`,
  `round`, `abs` ; variables scalaires uniquement : **aucune exécution de code**). Points arrondis, bornés à 0..100 000.
- `GamificationEngine` (logique pure, testée) décide ; `GamificationService` enregistre une ligne `Xp` par gain et les
  `VisiteurMedaille` (médaille `isUnique` : une seule fois par visiteur). Appelé à chaque `POST /api/launch/{jeton}/events`,
  résultat renvoyé dans `gamification` (`{ xp, medailles, totalXp }`, `null` sans visiteur identifié).
- Fonctionne **sans n8n ni Internet**. Une règle qui ne s'applique pas (variable absente, expression en erreur, médaille
  inconnue) est ignorée et journalisée, jamais bloquante.
- **Typologies** : facultatives, « Général » par défaut (créée automatiquement). Admin › Gamification : Médailles, Typologies.
- **Manifeste** : section `gamification` `{ regles, medailles }` ; à l'import, les règles par défaut ne sont posées que si
  l'interactif n'en a pas (jamais d'écrasement des réglages de l'admin), les médailles absentes sont créées.
- **Médaille demandée par l'interactif** (#8) : `POST /api/launch/{jeton}/medailles` `{medaille}` (JS :
  `ctx.demanderMedaille()`), visiteur rattaché requis, médaille listée dans `attribuables` des règles (anti-triche ;
  aucune par défaut), idempotent (unique : une fois par visiteur ; sinon une fois par partie), tracé dans le
  `LogVisite` (`navinum.medaille`).
- Le quiz affiche XP et médailles gagnés en fin de partie (#9).
- À venir : webhooks n8n (#10).

### n8n (automatisation, communication, IA)

n8n tourne sur le VPS (`https://n8n.srv802003.hstgr.cloud`, même réseau Docker que Navinum : appels internes).

- **Navinum → n8n** : webhooks sortants signés (HMAC), envoyés en asynchrone (Symfony Messenger + worker), déclarés dans
  une section « Intégrations » de l'admin. Événements : `medaille.obtenue`, `partie.terminee`, `visite.terminee`,
  `visiteur.inscrit`, `xp.palier`…
- **n8n → Navinum** : API protégée par clé d'intégration (Bearer) pour agir (attribuer une médaille, remplir une config,
  lire des statistiques). n8n peut ainsi porter des règles de gamification « intelligentes » en complément des règles JSON.
- Idées : mail de médaille ; **souvenir de visite** (récapitulatif, via `TemplateMail` / `SentMessage`, consentement
  `has_newsletter`) ; récapitulatif de groupe pour l'enseignant ; rapport hebdomadaire aux organisateurs ;
  **questions de quiz générées par IA** (validées par le JSON Schema, déposées en brouillon) ; traduction ; surveillance
  et alertes.
- Dev : prévoir un n8n local optionnel dans `docker-compose.dev.yaml` (règle « même stack partout »).

### Interactifs intelligents (IA)

- Interactifs dialoguant avec une IA : **jeux de rôle, jeux éducatifs**, personnages de musée, tuteur…
- Deux voies possibles : workflows **n8n** exposés en API (prototypage rapide, sans code) ou **Symfony AI**
  (`symfony/ai`) dans Navinum (contrôle fin, quotas, journalisation). Dans les deux cas, l'interactif passe par l'API
  Navinum (jamais de clé d'IA côté navigateur) et reçoit sa personnalité/ses consignes via sa config JSON Schema.

### Suivi

- Les tickets de la feuille de route sont sur GitHub (étiquettes `auth`, `gamification`, `n8n`, `ia`, `quiz`, `infra`,
  `dette-technique`), regroupés en **jalons de sprint** (Sprint 1 quiz gamifié, Sprint 2 comptes visiteurs, Sprint 3 n8n)
  et suivis sur le tableau **GitHub Project « Navinum »** (champs Status et Sprint).
- Modèles : tickets « Bug » (avec la règle « un bug = un nouveau test ») et « Fonctionnalité », modèle de PR avec la
  check-list du projet (`.github/`).
- `main` est protégée : fusion uniquement par PR, jobs de CI « Symfony » et « Librairie JS et quiz » obligatoires,
  conversations résolues, pas de force-push.
- Dependabot ouvre chaque semaine des PR de mise à jour (Composer, npm de la librairie ; mensuel pour Actions et Docker),
  testées par la CI ; alertes et correctifs de sécurité activés. La protection contre les secrets bloque tout push
  contenant un mot de passe ou une clé détectée.
- ⚠️ Le dépôt est **public** : ne jamais y écrire (code, docs, tickets, messages de commit) d'adresse IP, de port exposé,
  de secret ou de faille de sécurité non corrigée. Ces points se suivent hors du dépôt.

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

- 2026-11-21: 005-interactifs-config
  - Admin reconstruite en Symfony pur (Sylius retiré en janvier 2026), comptes en base + rôles admin/organisateur
  - Interactifs configurables par parcours (JSON Schema dans `variable`, `ParcoursInteractif`, formulaire générique)
  - API de lancement `/api/launch/{jeton}` + événements → LogVisite ; quiz branché (lib navinum-api 0.2+)
  - FrankenPHP : mode worker, Mercure intégré, Vulcain ; Caddyfile unique dev/prod
- 004-admin-parcours: Administration des Parcours avec Sylius (abandonné, remplacé par 005)

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
