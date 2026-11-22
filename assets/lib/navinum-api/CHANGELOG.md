# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

---

## [0.5.0] - 2026-11-22

La librairie devient le **seul point d'accès** des interactifs à Navinum : API, temps réel, identification.

### Added
- `api.rfidGroupes` : `list(params)`, `findByNom(nom)`, `create(nom)`, `findOrCreate(nom)` (JSON-LD géré par la
  librairie) et `onCreated(callback)` (Mercure, topic `rfid-groupes`).
- `api.sessions.subscribe(sessionId, callback, { onError })` : suivi temps réel d'une session (Mercure, topic
  `/navinum/sessions/{id}`), avec relecture de l'état à chaque (re)connexion. Remplace le polling.
- `api.visiteurs.identifier(pseudo)` : réutilise le visiteur conservé sur l'appareil (même pseudo, jeton valide) ou
  crée un invité ; `api.visiteurs.courant()` et `api.visiteurs.oublier()`. Premier élément du module d'authentification.
- `api.interactifs.chargerManifeste(url)` → `{ manifest, defaults }` et `InteractifAPI.defaultsFromSchema(schema)`.
- Options `mercureHub` (hub Mercure, défaut `/.well-known/mercure`) et `storage` (défaut `localStorage`, `null` pour
  ne rien conserver).
- Module interne `core/Realtime` : abonnement Mercure unique pour toute la librairie (`ctx.onConfigChange()` s'appuie
  dessus).
- Tests de la librairie (`npm test`, runner natif de Node) exécutés sur le bundle construit, lancés par la CI.

### Fixed
- Les réponses `application/ld+json` (API Platform) et `application/problem+json` n'étaient pas décodées en objet
  (renvoyées en texte brut).

---

## [0.4.0] - 2026-11-22

### Added
- `api.visiteurs.inviter(pseudo)` : crée un visiteur invité (`POST /api/visiteur/invite`) et renvoie
  `{ visiteur: { id, pseudo, anonyme }, token }` (jeton visiteur `nvv1.…`).
- `api.visiteurs.moi(token)` : profil du visiteur pour un jeton (`GET /api/visiteur/moi`), 401 si invalide.
- `api.launches.identify(token, visiteurToken)` et `ctx.identify(visiteurToken)` : rattache le visiteur au
  lancement (`POST /api/launch/{token}/visiteur`) ; ses événements sont ensuite enregistrés à son nom.

### Changed
- Les opérations CRUD `api.visiteurs` (`/api/visiteurs`) sont réservées à l'admin côté serveur (401 sinon).

### Deprecated
- `api.visiteurs.register()` et `api.visiteurs.getByRfid()` : endpoints inexistants, remplacés à terme par le
  module d'authentification (en ligne / RFID).

---

## [0.3.0] - 2026-11-21

### Added
- `ctx.mercure` dans le contexte de `api.launch()` : `{ hub, topics: { config } }` ou `null` (aperçu hors parcours).
- `ctx.onConfigChange(callback)` : écoute Mercure (`EventSource` sur `hub?topic=<topics.config>`) et appelle
  `callback(config)` à chaque enregistrement de la configuration dans l'admin. Retourne une fonction de
  désabonnement ; no-op (fonction vide) si `ctx.mercure` est null ou si `EventSource` est indisponible.
- `LaunchAPI.subscribeConfig(mercure, callback, options)` : variante bas niveau.

---

## [0.2.0] - 2026-11-21

### Added
- `api.launch(token?)` : récupère le contexte de lancement d'un interactif via le jeton `?nv=`
  (`GET /api/launch/{token}`). Retourne `{ token, interactif, parcours, exposition, config, visiteur, expiresAt, track(type, payload) }`,
  ou `null` si aucun jeton n'est présent dans l'URL (mode autonome).
- `ctx.track(type, payload)` : envoi d'un événement d'interactif (`POST /api/launch/{token}/events`).
- `LaunchAPI` (`api.launches`) : `get(token)`, `sendEvent(token, type, payload)`, `resolve(token)`,
  `LaunchAPI.getTokenFromUrl(search)`.
- `NavinumAPI.getLaunchToken(search?)` et `NavinumAPI.LAUNCH_TOKEN_PARAM` (`'nv'`).
- `GoneError` (HTTP 410), levée notamment pour un jeton de lancement expiré.

### Changed
- `ApiClient.handleError()` traduit désormais le statut 410 en `GoneError` (auparavant `NavinumAPIError` générique).

---

## [0.1.0] - 2025-01-17

### Added
- 🎉 Version initiale de la librairie Navinum API
- Core components :
  - `ApiClient` - Client HTTP avec fetch, retry automatique, intercepteurs
  - `Config` - Gestion de la configuration (baseURL, timeout, debug, retry)
  - `Errors` - Hiérarchie d'erreurs (NetworkError, ValidationError, UnauthorizedError, NotFoundError, ServerError)
- Resources API :
  - `SessionAPI` - Gestion des sessions multi-joueurs (create, join, leave, start, updateScore, reset, clearAll, get)
  - `ExpositionAPI` - CRUD expositions + getActive(), search()
  - `ParcoursAPI` - CRUD parcours + getByExposition()
  - `InteractifAPI` - CRUD interactifs + getByParcours(), logInteraction()
  - `VisiteurAPI` - CRUD visiteurs + register(), getByRfid()
  - `RfidAPI` - Scan et association de tags RFID
  - `LogVisiteAPI` - Tracking des visites + track(), getByVisiteur(), getStats()
- Pattern Resource avec BaseResource pour CRUD standard
- Build Webpack (dev et prod) avec Babel pour compatibilité navigateurs
- Mode debug avec logs console
- Retry automatique en cas d'erreur réseau (configurable)
- Timeout des requêtes configurable
- Intercepteurs de requête et réponse
- Export UMD pour utilisation navigateur
- Documentation README complète avec exemples
- Guide de contribution (CONTRIBUTING.md)

### Features principales
- ✅ Support tous les interactifs Navinum (quiz, jeux, parcours)
- ✅ Gestion d'erreurs typée et détaillée
- ✅ Configuration flexible
- ✅ Exemples réels complets (quiz multi-joueur, parcours RFID, dashboard)
- ✅ JSDoc complète sur toutes les méthodes
- ✅ Compatible IE11+ (après transpilation Babel)

### Tested
- ✅ Intégration et tests dans l'application Quiz
- ✅ Connexion au lobby (multi-joueurs)
- ✅ Démarrage de session
- ✅ Mise à jour des scores en temps réel
- ✅ Polling pour synchronisation
- ✅ Gestion des déconnexions

---

## [Unreleased]

### À venir (Roadmap)
- Publication sur npm
- Obfuscation du code en production
- Support TypeScript (.d.ts)
- Tests unitaires (Jest)
- Support WebSockets pour temps réel
- Cache intelligent des requêtes
- Support offline avec IndexedDB

---

## Format des versions

- **MAJOR** : Changements incompatibles avec les versions précédentes (breaking changes)
- **MINOR** : Ajout de fonctionnalités rétro-compatibles
- **PATCH** : Corrections de bugs rétro-compatibles

### Exemples de versioning

- `0.1.0` → `0.1.1` : Correction de bug
- `0.1.0` → `0.2.0` : Nouvelle fonctionnalité (ex: nouvelle ressource API)
- `0.1.0` → `1.0.0` : Breaking change (ex: modification de signature de méthode)

---

## Template pour nouvelles versions

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- Nouvelle fonctionnalité A
- Nouvelle méthode `api.resource.method()`

### Changed
- Modification du comportement de X
- Amélioration de la performance de Y

### Deprecated
- Méthode `api.old.method()` deprecated, utiliser `api.new.method()` à la place

### Removed
- Suppression de la méthode obsolète Z

### Fixed
- Correction du bug #123 où...
- Fix de l'erreur dans X quand...

### Security
- Correction de la vulnérabilité XYZ
```

---

**Note importante :** À chaque modification de la librairie ou des API Symfony, ce CHANGELOG DOIT être mis à jour. Voir [CONTRIBUTING.md](./CONTRIBUTING.md) pour plus de détails.
