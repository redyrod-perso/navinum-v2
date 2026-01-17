# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

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
