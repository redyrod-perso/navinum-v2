# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

---

## [0.13.0] - 2026-11-24

Connexion OAuth multi-fournisseurs (#4, partie 2).

### Added
- **Mode `oauth`** : `api.auth.connecter({ mode: 'oauth', fournisseur: 'google' | 'microsoft' | 'facebook' })`
  (page quittée vers le fournisseur) et **`api.auth.retourConnexion()`** au chargement de la page (échange du code à
  usage unique, rattachement de la partie). Un joueur connecté lie le fournisseur à son compte.
- `api.auth.fournisseursOAuth()` : fournisseurs activés sur le serveur.
- `api.visiteurs.fournisseursOAuth()`, `demarrerOAuth()`, `echangeOAuth()` ; code d'erreur `CONNEXION_ANNULEE`.
- Exemple d'identification : boutons « Continuer avec … » et gestion du retour.

---

## [0.12.0] - 2026-11-24

Comptes en ligne (#4, partie 1).

### Added
- **Mode `compte`** : `api.auth.connecter({ mode: 'compte', email, motDePasse })` ; sans mot de passe, reprise de la
  session du compte conservée sur l'appareil.
- `api.auth.creerCompte({ email, motDePasse, pseudo?, newsletter? })` : un invité connecté devient un compte et garde
  ses XP, médailles et historique.
- `api.visiteurs.inscription()`, `connexion()` ; codes d'erreur `EMAIL_DEJA_UTILISE`, `IDENTIFIANTS_INVALIDES`,
  `TROP_DE_TENTATIVES` (statut 429).
- Exemple d'identification : formulaire de compte (création depuis un invité, connexion).

---

## [0.11.0] - 2026-11-24

Module d'authentification finalisé (#3).

### Added
- **Contrat des modes de connexion** : `api.auth.ajouterMode(nom, { connecter, entretenir?, deconnecter? })`. La
  prolongation (`entretenir()`) et la déconnexion passent par le mode du joueur (avant : toujours le mode invité),
  prêt pour le compte en ligne, OAuth (#4) et le badge RFID (#5). Une simple fonction reste acceptée.
- **Exemple d'intégration** sans React : `public/exemples/identification/index.html`.

### Fixed
- `NavinumAPI.erreurs.decrire()` remplaçait le message précis d'une erreur levée par la librairie elle-même
  (ex. « Le pseudo doit faire entre 2 et 40 caractères ») par le message générique du catalogue.

---

## [0.10.0] - 2026-11-24

Modules réutilisables, partie 2 (#53).

### Added
- **Module multijoueur** `api.multijoueur` : `partie({ objectif })` → `rejoindre()`, `demarrer()`, `marquerPoint()`,
  `terminer()`, `quitter()`, `etat()`, `onChange()` ; `api.multijoueur.groupes` (`lister()`, `trouver()`,
  `trouverOuCreer()`, `onCree()`). Logique extraite du quiz
  (lobby, leader, démarrage synchronisé, scores en direct, premier à l'objectif gagnant, départ à la fermeture).
- **Couche React facultative** : `NavinumAPI.creerHooksReact(React)` → `usePartie`, `useJoueur`, `useGains`.
- Points d'entrée `@navinum/api/multijoueur` et `@navinum/api/react`.

### Changed
- `resources/RfidGroupeAPI.js` ne fait plus que les appels HTTP (`list`, `create`, `onCreated`) ; la recherche par
  nom et la création à la demande passent dans le module multijoueur (`api.multijoueur.groupes`).

### Deprecated
- `api.rfidGroupes.findByNom()` et `findOrCreate()` : utiliser `api.multijoueur.groupes.trouver()` et
  `trouverOuCreer()`.

### Fixed
- Un lien de lancement réutilisé par un autre joueur, alors que le précédent était encore actif, créditait le
  joueur précédent. Les événements et médailles demandées portent désormais le jeton du joueur connecté
  (`Authorization`) ; le serveur ne crédite que le joueur rattaché (sinon `rattache: false`, personne n'est crédité,
  `api.gamification.evenement()` rend `null`). `api.auth.connecter()` indique `rattache: false` et
  `motif: 'DEJA_RATTACHE'` pour que l'interactif prévienne le joueur.
- `rfidGroupes.list()` ne rendait que les 30 premiers groupes (pagination API Platform) : un groupe récent
  n'apparaissait pas dans les listes, et `findByNom()` / `findOrCreate()` pouvaient le manquer. Toutes les pages
  sont désormais lues.

---

## [0.9.0] - 2026-11-24

Modules réutilisables, partie 1 (#53).

### Added
- **Module gamification** `api.gamification` : `evenement(type, payload)` (gains ou null, jamais bloquant),
  `demanderMedaille()`, `onGains()`, `bilan()`.
- Points d'entrée par module pour les bundlers (`package.json` › `exports`) : `@navinum/api/erreurs`,
  `@navinum/api/auth`, `@navinum/api/gamification`.

### Changed
- Un dossier par module (`src/modules/erreurs`, `auth`, `gamification`). La logique de session (reprise sur
  l'appareil, prolongation, stockage) quitte `resources/VisiteurAPI.js` pour `modules/auth` (`ModeInvite`,
  `Stockage`) : les ressources ne font plus que les appels HTTP.

### Deprecated
- `api.visiteurs.identifier()`, `courant()`, `oublier()` : utiliser `api.auth.connecter()`, `joueur()`, `oublier()`.

---

## [0.8.0] - 2026-11-23

Sessions visiteur à durée limitée (#6).

### Added
- Jeton visiteur `nvv2` : **30 min d'inactivité**, **12 h au plus**, révocable. `api.auth` le prolonge à chaque
  événement de la partie (`ctx.track()` → `api.auth.entretenir()`).
- Reprise sur le même appareil : après expiration, `connecter()` / `visiteurs.identifier()` avec le même pseudo
  retrouve le même profil grâce au secret d'appareil conservé (`nva1.…`).
- `api.auth.connecter({ memoriser: false })` (borne partagée), `api.auth.oublier()` (« Ce n'est pas moi »).
- `api.visiteurs.reprendre()`, `renouveler()`, `deconnexion()`.

### Changed
- `api.auth.deconnecter()` est asynchrone et **révoque la session côté serveur** (avant : oubli local seulement,
  désormais `api.auth.oublier()`).
- Les anciens jetons `nvv1` ne sont plus acceptés comme jetons d'accès ; la librairie les échange une fois contre un
  appareil (les visiteurs existants gardent leur profil).

---

## [0.7.0] - 2026-11-23

### Added
- `ctx.demanderMedaille(libelle)` / `api.launches.demanderMedaille(token, libelle)` : médaille demandée par
  l'interactif (`POST /api/launch/{jeton}/medailles`), limitée aux médailles `attribuables` de ses règles (#8).
- `sessions.create()` / `join()` acceptent `launchToken` : le serveur impose `joueursMax` du lancement (#17).
- Codes d'erreur `SCORE_REFUSE`, `MEDAILLE_NON_ATTRIBUABLE`, `MEDAILLE_INCONNUE`.

---

## [0.6.0] - 2026-11-22

Deux modules transverses pour tous les interactifs : erreurs et authentification.

### Added
- **Module d'erreurs** `NavinumAPI.erreurs` (aussi `api.erreurs`) : `decrire(err)` → `{ code, titre, message,
  reessayable, action, status, details, erreur }`, codes stables indépendants des statuts HTTP (`RESEAU`,
  `LIEN_EXPIRE`, `PARTIE_EN_COURS`, `GROUPE_COMPLET`…), `est(err, ...codes)`, `creer(code, message)`. Aucun affichage.
- `api.onError(callback)` : appelé pour chaque erreur d'API (après les nouvelles tentatives), avec l'erreur décrite.
- **Module d'authentification** `api.auth` : `connecter({ mode, pseudo })` (mode `invite` ; `compte`, `oauth`, `rfid`
  à venir), `joueur()`, `deconnecter()`, `onChange()`, `modes()`, `ajouterMode()`. Rattache automatiquement la partie
  lancée par `api.launch()` au joueur.
- `api.sessions.quitterEnPartant(sessionId, { playerName })` : quitte le groupe à la fermeture de la page
  (requête `keepalive`).
- Les erreurs portent `code` (code métier du serveur) et `body` (corps de la réponse).

### Fixed
- Les noms de groupe contenant `?`, `#` ou `%` cassaient les URL de session : ils sont désormais encodés.
- Le minuteur de timeout n'était pas libéré quand `fetch` échouait (process Node maintenu 30 s, minuteurs inutiles
  dans le navigateur).
- `error.status` vaut le statut HTTP réel (ex. 422 et non 400 pour une `ValidationError` d'API Platform).

---

## [0.5.1] - 2026-11-23

### Fixed
- Les erreurs renvoyées par le serveur perdaient leur message : seule une propriété `message` était lue, alors que
  Navinum renvoie `{ error }` et API Platform `{ detail }`. Le message réel est désormais transmis
  (ex. « Une partie est en cours dans ce groupe… » au lieu de « Données invalides »).
- 422 (validation API Platform) traité comme `ValidationError` ; pour les autres codes (409, 403…), le code HTTP est
  disponible dans `error.status`.

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
