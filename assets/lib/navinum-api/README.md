# Navinum API Client

Librairie JavaScript complète pour interagir avec les APIs Symfony de Navinum. Supporte tous les interactifs (quiz, jeux, parcours, etc.) avec gestion des erreurs, retry automatique et mode debug.

> **Principe : c'est le seul point d'accès d'un interactif à Navinum.** API REST, temps réel (Mercure),
> identification du joueur, lancement et configuration passent tous par cette librairie. Un interactif ne fait
> **jamais** de `fetch`, d'`EventSource` ni de `localStorage` direct (la CI de Navinum le vérifie). C'est ce qui
> permet aux développeurs tiers de créer un interactif sans connaître les détails de l'API, et à Navinum de faire
> évoluer ces détails (authentification OAuth / RFID, nouveaux endpoints) sans casser les interactifs.

---

## Table des matières

- [Installation](#installation)
- [Quick Start](#quick-start)
- [API Usage](#api-usage)
- [Modules réutilisables](#modules-réutilisables)
- [Authentification des joueurs](#authentification-des-joueurs)
- [Gamification](#gamification)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Configuration avancée](#configuration-avancée)
- [Exemples réels](#exemples-réels)
- [Développement](#développement)
- [Contribuer](#contribuer)

---

## Installation

### Méthode 1 : Inclusion directe dans le HTML (Recommandé pour le développement)

```html
<!DOCTYPE html>
<html>
<head>
    <title>Mon Interactif</title>
</head>
<body>
    <!-- Incluez la librairie avant votre code -->
    <script src="/build/navinum-api.js"></script>

    <script>
        // La classe NavinumAPI est maintenant disponible globalement
        const api = new NavinumAPI({
            debug: true
        });

        // Utiliser l'API
        api.sessions.create({ playerName: 'Alice' })
            .then(data => console.log('Session créée:', data))
            .catch(err => console.error('Erreur:', err));
    </script>
</body>
</html>
```

### Méthode 2 : Build depuis les sources

```bash
# 1. Aller dans le dossier de la librairie
cd assets/lib/navinum-api

# 2. Installer les dépendances
npm install

# 3. Builder la librairie
npm run build          # Build dev + prod
# ou
npm run build:dev      # Build développement uniquement
npm run build:prod     # Build production (minifié) uniquement

# 4. Copier le fichier build dans votre projet
cp dist/navinum-api.js /path/to/your/public/build/
```

### Méthode 3 : Via npm (Futur - après publication)

```bash
npm install @navinum/api
```

Puis dans votre code :

```javascript
// ESM
import NavinumAPI from '@navinum/api';

// CommonJS
const NavinumAPI = require('@navinum/api');
```

---

## Quick Start

### Exemple simple : Créer et rejoindre une session de quiz

```javascript
// 1. Initialiser l'API
const api = new NavinumAPI({
    baseURL: 'http://localhost:8002',  // URL de votre backend Symfony
    debug: true                         // Voir les requêtes dans la console
});

// 2. Créer une session
const createSession = async () => {
    try {
        const result = await api.sessions.create({
            playerName: 'Alice',
            sessionId: 'global'
        });

        console.log('Session créée:', result.session);
        console.log('Joueurs:', result.session.players);
    } catch (error) {
        console.error('Erreur:', error.message);
    }
};

// 3. Rejoindre une session existante
const joinSession = async () => {
    try {
        const result = await api.sessions.join('global', {
            playerName: 'Bob'
        });

        console.log('Session rejointe:', result.session);
    } catch (error) {
        if (error instanceof NavinumAPI.NotFoundError) {
            console.error('Session introuvable');
        }
    }
};

// Exécuter
createSession();
```

### Exemple : Gérer un parcours avec RFID

```javascript
const api = new NavinumAPI({ debug: true });

// Scanner un badge RFID
const scanBadge = async (tagId) => {
    try {
        const result = await api.rfid.scan(tagId);
        console.log('Tag scanné:', result);

        // Récupérer le visiteur associé
        const visiteur = await api.visiteurs.getByRfid(tagId);
        console.log('Visiteur:', visiteur);

        // Logger la visite
        await api.logVisites.track({
            visiteurId: visiteur.id,
            action: 'scan_badge',
            data: { tagId }
        });

        return visiteur;
    } catch (error) {
        console.error('Erreur scan:', error);
    }
};

// Utilisation
scanBadge('TAG123456');
```

---

## API Usage

### Sessions (Quiz, Jeux multi-joueurs)

#### Créer une session

```javascript
const session = await api.sessions.create({
    playerName: 'Alice',      // Nom du joueur créateur
    sessionId: 'global',      // ID optionnel de la session
    theme: 'sciences'         // Thème optionnel
});

// Retour :
// {
//   session: {
//     id: 'global',
//     players: [{ name: 'Alice', score: 0 }],
//     status: 'waiting',
//     theme: null
//   }
// }
```

#### Rejoindre une session

```javascript
const result = await api.sessions.join('global', {
    playerName: 'Bob'
});

// La session contient maintenant 2 joueurs
```

#### Démarrer le jeu

```javascript
await api.sessions.start('global', {
    theme: 'sciences'
});

// Status passe de 'waiting' à 'playing'
```

#### Mettre à jour le score

```javascript
await api.sessions.updateScore('global', {
    playerName: 'Alice',
    score: 5
});
```

#### Récupérer l'état de la session

```javascript
const data = await api.sessions.get('global');
console.log('Joueurs:', data.session.players);
console.log('Status:', data.session.status);
```

#### Suivre la session en temps réel (Mercure)

Pas de polling : la librairie s'abonne au hub Mercure (topic `/navinum/sessions/{id}`), relit l'état à chaque
(re)connexion, puis transmet chaque changement (joueur qui rejoint, démarrage, scores, départ).

```javascript
const stop = api.sessions.subscribe('Classe 4B', session => {
    afficherJoueurs(session.players);
    if (session.status === 'playing') demarrer(session.theme);
}, { onError: err => console.warn('Relecture impossible', err) });

// en quittant le lobby ou la partie
stop();
```

#### Quitter une session

```javascript
await api.sessions.leave('global', {
    playerName: 'Bob'
});
```

#### Réinitialiser ou supprimer

```javascript
// Réinitialiser une session
await api.sessions.reset('global');

// Supprimer toutes les sessions
await api.sessions.clearAll();
```

---

#### Quitter le groupe à la fermeture de la page

```javascript
// Dans le lobby ou en partie : le joueur quitte le groupe si la page est fermée ou rechargée
const stop = api.sessions.quitterEnPartant('Classe 4B', { playerName: 'Léa' });
// départ normal : retirer l'écouteur puis quitter
stop();
await api.sessions.leave('Classe 4B', { playerName: 'Léa' });
```

#### Nombre maximum de joueurs (imposé par le serveur)

Transmettre le jeton de lancement à `create()` / `join()` : le serveur applique `joueursMax` de la configuration du
parcours (8 sans jeton, 50 au plus) et refuse le joueur suivant avec `GROUPE_COMPLET`.

```javascript
await api.sessions.join('Classe 4B', { playerName: 'Léa', launchToken: NavinumAPI.getLaunchToken() });
```

`updateScore()` n'accepte qu'une progression plausible : partie en cours, joueur du groupe, +1 au plus par appel
(sinon `SCORE_REFUSE`).

Les noms de groupe sont encodés dans l'URL (`?`, `#`, espaces, accents…). `reset()` et `clearAll()` sont réservés à
l'administration.

### Expositions

#### Lister toutes les expositions

```javascript
const expositions = await api.expositions.getAll();
// Retourne un tableau d'expositions
```

#### Récupérer une exposition par ID

```javascript
const expo = await api.expositions.getById(1);
console.log('Nom:', expo.nom);
console.log('Description:', expo.description);
```

#### Créer une exposition

```javascript
const newExpo = await api.expositions.create({
    nom: 'Expo 2025',
    description: 'Nouvelle exposition temporaire',
    dateDebut: '2025-01-01',
    dateFin: '2025-12-31'
});
```

#### Mettre à jour une exposition

```javascript
// Update complet (PUT)
await api.expositions.update(1, {
    nom: 'Expo 2025 Modifiée',
    description: 'Description mise à jour'
});

// Update partiel (PATCH)
await api.expositions.patch(1, {
    nom: 'Nouveau nom'
});
```

#### Supprimer une exposition

```javascript
await api.expositions.delete(1);
```

#### Récupérer l'exposition active

```javascript
const active = await api.expositions.getActive();
console.log('Exposition active:', active.nom);
```

#### Rechercher des expositions

```javascript
const results = await api.expositions.search({
    nom: 'Art',
    annee: 2025
});
```

---

### Parcours

#### Lister tous les parcours

```javascript
const parcours = await api.parcours.getAll();
```

#### Récupérer les parcours d'une exposition

```javascript
const parcours = await api.parcours.getByExposition(1);
console.log(`${parcours.length} parcours disponibles`);
```

#### CRUD standard

```javascript
// Récupérer un parcours
const p = await api.parcours.getById(1);

// Créer un parcours
const newParcours = await api.parcours.create({
    nom: 'Parcours découverte',
    expositionId: 1,
    duree: 45
});

// Mettre à jour
await api.parcours.update(1, { duree: 60 });

// Supprimer
await api.parcours.delete(1);
```

---

### Interactifs (Jeux, Quiz, Points d'intérêt)

#### Lister tous les interactifs

```javascript
const interactifs = await api.interactifs.getAll();
```

#### Récupérer les interactifs d'un parcours

```javascript
const interactifs = await api.interactifs.getByParcours(1);

interactifs.forEach(interactif => {
    console.log('Interactif:', interactif.nom);
    console.log('Type:', interactif.type);
});
```

#### Charger le manifeste et sa configuration par défaut

```javascript
// Valeurs `default` du configSchema de navinum.json (mode autonome, base de fusion)
const { manifest, defaults } = await api.interactifs.chargerManifeste('/quiz/navinum.json');
const ctx = await api.launch();
const config = ctx ? { ...defaults, ...ctx.config } : defaults;
```

#### Enregistrer une interaction

```javascript
await api.interactifs.logInteraction(5, {
    visiteurId: 123,
    data: {
        score: 10,
        tempsPassé: 120,
        reponses: ['A', 'B', 'C']
    }
});
```

---

### Visiteurs

Un interactif identifie son joueur par un **jeton visiteur** (`nvv1.…`). Pour le prototype, il s'obtient en créant
un **visiteur invité** à partir d'un pseudo ; le futur module d'authentification (compte en ligne, badge RFID) délivrera
le même jeton, sans changement pour les interactifs.

#### Identifier le joueur (recommandé)

`identifier()` réutilise le visiteur conservé sur l'appareil s'il porte le même pseudo et que son jeton est valide,
sinon crée un visiteur invité et le conserve. Si l'API est injoignable, le visiteur connu est gardé.

```javascript
const joueur = await api.visiteurs.identifier('Alice');   // { id, pseudo, anonyme, token }
const pseudoConnu = api.visiteurs.courant()?.pseudo;        // sans appel réseau (pré-remplir le formulaire)
api.visiteurs.oublier();                                   // « ce n'est pas moi » / déconnexion
```

Le stockage se règle à la création du client : `new NavinumAPI({ storage: sessionStorage })`, ou `storage: null`
pour ne rien conserver. Bas niveau : `api.visiteurs.inviter(pseudo)` et `api.visiteurs.moi(token)`.

#### Rattacher la partie au visiteur

```javascript
const ctx = await api.launch();          // interactif lancé par Navinum (?nv=)
if (ctx) await ctx.identify(joueur.token); // POST /api/launch/{token}/visiteur (joueur = api.visiteurs.identifier(…))
// les événements ctx.track(...) sont désormais enregistrés au nom du visiteur (LogVisite)
```

> Le profil renvoyé (`{ id, pseudo, anonyme }`) ne contient jamais d'email ni de donnée personnelle.
> Les opérations CRUD `api.visiteurs.getAll()`, `getById()`, `update()`… (`/api/visiteurs`) sont réservées à
> l'admin : elles renvoient 401 depuis un interactif. `register()` et `getByRfid()` sont dépréciées (endpoints
> inexistants) en attendant le module d'authentification.

---

### Groupes de visiteurs

Classe, famille, équipe… Dans le quiz, le groupe sert aussi d'identifiant de session commun.
Lecture et création publiques ; modification et suppression réservées à l'admin.

```javascript
const groupes = await api.rfidGroupes.list();                    // [{ id, nom }, …]
const groupe = await api.rfidGroupes.findByNom('Classe 4B');     // nom exact, ou null
const cree = await api.rfidGroupes.create('Classe 4B');          // ValidationError si nom vide
const g = await api.rfidGroupes.findOrCreate('Classe 4B');       // l'un ou l'autre

// Nouveaux groupes en direct (Mercure, topic `rfid-groupes`)
const stop = api.rfidGroupes.onCreated(groupe => rafraichirListe());
```

---

### RFID

#### Scanner un tag RFID

```javascript
const result = await api.rfid.scan('TAG123456');
console.log('Tag scanné:', result);
```

#### Associer un tag à une ressource

```javascript
// Associer un tag à un visiteur
await api.rfid.associate('TAG123456', {
    resourceType: 'visiteur',
    resourceId: 123
});

// Associer un tag à un objet
await api.rfid.associate('TAG789', {
    resourceType: 'objet',
    resourceId: 456
});
```

---

### Lancement d'un interactif (jeton `?nv=`)

> Depuis la version 0.2.0

Navinum ouvre un interactif via `<url de l'interactif>?nv=<token>`. Le jeton donne accès au
**contexte de lancement** : l'interactif, le parcours, l'exposition, le visiteur éventuel et
surtout la **configuration fusionnée** (valeurs saisies pour le parcours par-dessus les `default`
du `configSchema` déclaré par l'interactif dans son manifeste `navinum.json`).

#### Récupérer le contexte

```javascript
const api = new NavinumAPI();

// Lit automatiquement ?nv= dans window.location.search
const ctx = await api.launch();

// ...ou avec un jeton explicite
// const ctx = await api.launch('abc123');

if (ctx) {
    console.log(ctx.token);       // 'abc123'
    console.log(ctx.interactif);  // { id, libelle, version }
    console.log(ctx.parcours);    // { id, libelle } | null
    console.log(ctx.exposition);  // { id, libelle } | null
    console.log(ctx.config);      // { tempsParQuestion: 20, nbQuestions: 10, ... }
    console.log(ctx.visiteur);    // { id, pseudo, anonyme } | null
    console.log(ctx.expiresAt);   // '2026-11-21T18:00:00+01:00'
    console.log(ctx.mercure);     // { hub, topics: { config } } | null (0.3.0+)
} else {
    // Aucun ?nv= : interactif lancé hors Navinum (mode autonome / dev)
    // -> utiliser les valeurs `default` du configSchema
}
```

`api.launch()` retourne `null` (sans appel réseau) quand aucun jeton n'est présent.
Pour simplement tester la présence d'un jeton : `NavinumAPI.getLaunchToken()` (retourne le jeton ou `null`).

#### Envoyer des événements

```javascript
await ctx.track('quiz.started', { theme: 'histoire' });
await ctx.track('quiz.completed', { theme: 'histoire', score: 8, total: 10 });
// -> POST /api/launch/{token}/events  { "type": "...", "payload": { ... } }  => 202 { accepted: true }
```

Les types d'événements doivent être déclarés dans la clé `events` du manifeste `navinum.json` de l'interactif.

#### Suivre les modifications de configuration en direct (Mercure)

> Depuis la version 0.3.0

Quand un organisateur enregistre la configuration dans l'admin, Navinum publie
`{"config": { …config effective complète… }}` sur le topic `ctx.mercure.topics.config`.

```javascript
const unsubscribe = ctx.onConfigChange(config => {
    // Nouvelle configuration complète (même forme que ctx.config)
    console.log('Configuration mise à jour', config);
});

// Pour arrêter l'écoute (ferme l'EventSource)
unsubscribe();
```

- Ouvre un `EventSource` sur `<hub>?topic=<topics.config>` (topic URL-encodé), abonnement anonyme.
- `callback(config)` est appelé à chaque message contenant un objet `config` ; les messages illisibles sont ignorés.
- Si `ctx.mercure` est `null` (aperçu hors parcours) ou si `EventSource` n'existe pas, c'est un no-op
  qui retourne une fonction vide : on peut appeler `unsubscribe()` sans test préalable.
- Variante bas niveau : `LaunchAPI.subscribeConfig(mercure, callback)`.

#### Erreurs

```javascript
try {
    const ctx = await api.launch();
} catch (error) {
    if (error instanceof NavinumAPI.GoneError) {
        // 410 : jeton expiré
    } else if (error instanceof NavinumAPI.NotFoundError) {
        // 404 : jeton inconnu
    } else if (error instanceof NavinumAPI.NetworkError) {
        // serveur injoignable
    }
}
```

#### Accès bas niveau

```javascript
const data = await api.launches.get('abc123');                          // GET /api/launch/{token}
await api.launches.sendEvent('abc123', 'quiz.completed', { score: 8 }); // POST /api/launch/{token}/events
```

---

#### Médaille demandée par l'interactif

```javascript
const ctx = await api.launch();
await api.auth.connecter({ pseudo: 'Léa' });          // le visiteur doit être rattaché au lancement
const { medaille, nouvelle, totalXp } = await ctx.demanderMedaille('Explorateur');
```

Anti-triche : seules les médailles listées dans `attribuables` des règles de gamification de l'interactif
(admin › Interactifs › 🏆, ou manifeste) sont accordées (`MEDAILLE_NON_ATTRIBUABLE` sinon). Idempotent : une médaille
unique s'obtient une fois par visiteur (`nouvelle: false` ensuite), une autre au plus une fois par partie.

### Log Visites (Analytics, Tracking)

#### Enregistrer une action

```javascript
await api.logVisites.track({
    visiteurId: 123,
    action: 'view_interactif',
    data: {
        interactifId: 5,
        duree: 120,
        score: 10
    }
});

// Actions courantes :
// - 'view_interactif'
// - 'scan_badge'
// - 'start_parcours'
// - 'complete_parcours'
// - 'answer_question'
```

#### Récupérer les logs d'un visiteur

```javascript
const logs = await api.logVisites.getByVisiteur(123, {
    dateDebut: '2024-01-01',
    dateFin: '2024-12-31',
    action: 'view_interactif'  // Filtre optionnel
});

logs.forEach(log => {
    console.log('Action:', log.action);
    console.log('Date:', log.timestamp);
    console.log('Data:', log.data);
});
```

#### Récupérer les statistiques

```javascript
const stats = await api.logVisites.getStats({
    dateDebut: '2024-01-01',
    dateFin: '2024-12-31',
    expositionId: 1  // Optionnel
});

console.log('Nombre de visiteurs:', stats.totalVisiteurs);
console.log('Actions totales:', stats.totalActions);
console.log('Top interactifs:', stats.topInteractifs);
```

---

## Modules réutilisables

La librairie est faite de **modules sans HTML ni design**, réutilisables par n'importe quel interactif (le quiz
n'en est qu'un exemple) :

| Module | Accès | Rôle |
|--------|-------|------|
| socle | `new NavinumAPI()`, `api.launch()` | configuration, appels HTTP, temps réel Mercure, lancement `?nv=` |
| erreurs | `NavinumAPI.erreurs` | codes stables, messages, actions conseillées |
| auth | `api.auth` | identification, sessions (30 min / 12 h), reprise sur l'appareil, mode borne |
| gamification | `api.gamification` | événements de la partie, XP et médailles gagnés, médaille demandée |
| multijoueur | `api.multijoueur` | groupe, lobby, leader, démarrage synchronisé, scores en direct, gagnant, départs |
| React *(facultatif)* | `NavinumAPI.creerHooksReact(React)` | hooks `usePartie`, `useJoueur`, `useGains` (logique, pas de design) |

**Interactif HTML** : un seul fichier, tous les modules — `<script src="/build/navinum-api.js"></script>`.

**Avec un bundler** (Vite, webpack…) : chaque module a son propre point d'entrée.

```javascript
import NavinumAPI from '@navinum/api';
import { erreurs } from '@navinum/api/erreurs';           // utilisable seul, sans instance
import { Auth } from '@navinum/api/auth';                 // classes, pour composer ou étendre
import { Gamification } from '@navinum/api/gamification';
import { Partie } from '@navinum/api/multijoueur';
import { creerHooks } from '@navinum/api/react';
```

Pas de fichier `<script>` par module : chacun embarquerait le socle (code chargé plusieurs fois, versions
incompatibles possibles sur une même page).

## Authentification des joueurs

Module `api.auth` : **une seule API pour tous les modes de connexion**. Il rend un joueur et son **jeton visiteur**
(`nvv1.…`) et, si l'interactif a été lancé par Navinum (`await api.launch()`), **rattache automatiquement la partie**
au joueur (ses événements alimentent son parcours, ses XP et ses médailles).

```javascript
const ctx = await api.launch();                              // facultatif (mode autonome : null)
const joueur = await api.auth.connecter({ pseudo: 'Léa' });  // mode « invite » par défaut
// { id, pseudo, anonyme: true, token: 'nvv1.…', mode: 'invite', rattache: true }

api.auth.joueur();          // joueur connecté (ou conservé sur l'appareil), sans appel réseau
api.auth.onChange(j => …);  // connexion / déconnexion ; retourne la fonction de désabonnement
api.auth.deconnecter();     // « ce n'est pas moi » : oublie le joueur sur l'appareil
api.auth.modes();           // ['invite']
```

| Mode | État |
|------|------|
| `invite` | Disponible : visiteur invité à partir d'un pseudo, réutilisé sur l'appareil |
| `compte`, `oauth`, `rfid` | À venir : erreur `MODE_INDISPONIBLE` en attendant |
| personnalisé | `api.auth.ajouterMode(nom, async (options, api) => ({ id, pseudo, anonyme, token }))` |

**Session (lib 0.8.0) :** le jeton expire après **30 minutes d'inactivité** et au plus **12 heures** après
l'identification. La librairie le prolonge toute seule à chaque événement de la partie (`ctx.track()`). Après
expiration, `connecter()` avec le même pseudo **sur le même appareil** retrouve le même profil (XP, médailles) grâce
au secret d'appareil qu'elle conserve ; sur un autre appareil, le joueur repart à zéro (compte en ligne à venir).

| Méthode | Effet |
|---------|-------|
| `api.auth.connecter({ pseudo, memoriser })` | `memoriser: false` = **borne partagée** : rien n'est conservé sur l'appareil |
| `await api.auth.deconnecter()` | révoque la session côté serveur et oublie le joueur (fin de partie sur une borne) |
| `api.auth.oublier()` | oublie le joueur sur l'appareil sans révoquer (« Ce n'est pas moi ») |
| `api.auth.entretenir()` | prolonge la session si elle expire bientôt (appelé automatiquement par `ctx.track()`) |

Sur une borne, activer `modeBorne` dans la configuration du placement : le serveur laisse alors toujours le
joueur suivant reprendre le lancement (un joueur parti sans se déconnecter ne reçoit pas ses gains).

Le rattachement de la partie n'est jamais bloquant : en cas d'échec, `joueur.rattache === false` et le joueur reste
connecté. Le stockage se règle avec l'option `storage` (`localStorage` par défaut, `null` pour ne rien conserver).

---

## Gamification

Module `api.gamification`, sans affichage. Les XP et médailles se décident côté serveur, selon les règles de
l'interactif (admin › Interactifs › 🏆, ou section `gamification` du manifeste).

```javascript
await api.launch();                                  // lancé par Navinum (?nv=)
await api.auth.connecter({ pseudo: 'Léa' });         // joueur identifié

api.gamification.onGains(gains => afficher(gains));  // à chaque XP ou nouvelle médaille
const gains = await api.gamification.evenement('quiz.completed', { score: 8, total: 10 });
// { xp: [{ typologie: 'Joueur', points: 80 }], medailles: [{ libelle: 'Sans faute', … }], totalXp: 240 }
await api.gamification.demanderMedaille('Explorateur'); // médaille « attribuable » par l'interactif
api.gamification.bilan();                            // { xp, medailles, totalXp } depuis l'ouverture de la page
```

- `evenement()` est **jamais bloquant** : en mode autonome (sans `?nv=`), sans joueur identifié ou en cas d'erreur
  réseau, il rend `null` et le jeu continue. Chaque appel prolonge aussi la session du joueur.
- `demanderMedaille()` lève les refus (`MEDAILLE_NON_ATTRIBUABLE`, `NON_IDENTIFIE`…, voir le module d'erreurs).

## Multijoueur

Module `api.multijoueur`, sans affichage : tout ce qu'il faut pour un jeu à plusieurs dans un groupe. Règle par
défaut : **le premier qui atteint l'objectif gagne**, et la partie se termine pour tout le groupe.

```javascript
const partie = api.multijoueur.partie({ objectif: theme => questions(theme).length }); // ou un nombre

partie.onChange((etat, avant) => {
    // etat : { etape: 'hors'|'lobby'|'en_cours'|'terminee', groupe, moi, joueurs: [{ nom, score, leader, moi }],
    //          leader, joueursMax, theme, objectif, score, gagnant, victoire, debut }
    if (avant.etape === 'lobby' && etat.etape === 'en_cours') afficherQuestions(etat.theme);
    if (etat.etape === 'terminee') afficherPodium(etat.joueurs, etat.gagnant);
});

await partie.rejoindre('Classe 4B', 'Léa');     // groupe créé si besoin ; lobby (GROUPE_COMPLET, PARTIE_EN_COURS…)
if (partie.etat().leader) await partie.demarrer('histoire'); // tous les joueurs démarrent ensemble
await partie.marquerPoint();                    // bonne réponse : +1 (contrôlé par le serveur)
partie.terminer();                              // fini sans atteindre l'objectif
await partie.quitter();                         // aussi automatique à la fermeture de la page

await api.multijoueur.groupes();                // groupes existants (toutes les pages)
api.multijoueur.onGroupeCree(g => …);           // groupes créés en direct
```

- Le jeton de lancement (`api.launch()`) est transmis : le serveur impose le nombre maximum de joueurs du parcours.
- Après la fin, les scores continuent d'arriver (joueurs qui finissent après nous) ; notre score n'est jamais écrasé
  par un état plus ancien.

### Avec React

```javascript
const { usePartie, useJoueur, useGains } = NavinumAPI.creerHooksReact(React);

function Lobby({ partie }) {
    const etat = usePartie(partie);   // re-rendu à chaque changement
    return <ul>{etat.joueurs.map(j => <li key={j.nom}>{j.leader ? '👑' : '👤'} {j.nom}</li>)}</ul>;
}
```

Exemple complet : le quiz (`public/quiz/app.jsx`), qui ne contient plus que son affichage et ses questions.

## Gestion des erreurs

### Module d'erreurs (recommandé)

`NavinumAPI.erreurs` (ou `api.erreurs`) transforme **n'importe quelle erreur** en description exploitable, **sans
HTML ni design** : chaque interactif choisit sa présentation.

```javascript
try {
    await api.sessions.join('Classe 4B', { playerName: 'Léa' });
} catch (err) {
    const info = NavinumAPI.erreurs.decrire(err);
    // { code: 'PARTIE_EN_COURS', titre: 'Partie en cours',
    //   message: 'Une partie est en cours dans ce groupe : attendez la fin ou choisissez un autre groupe.',
    //   reessayable: true, action: 'CHOISIR_AUTRE_GROUPE', status: 409, details: {…}, erreur: err }
    afficherBandeau(info.titre, info.message, info.reessayable);
}

NavinumAPI.erreurs.est(err, 'LIEN_EXPIRE', 'LIEN_INVALIDE');               // test par code
throw NavinumAPI.erreurs.creer('GROUPE_COMPLET', 'Ce groupe est complet.');   // erreur propre à l'interactif
api.onError(info => console.warn(info.code, info.message));                  // toutes les erreurs d'API (journal, télémétrie)
```

Le message est celui du serveur quand il en fournit un (en français, plus précis), sinon celui du catalogue.
Le serveur peut imposer le code : `{ "error": "…", "code": "PARTIE_EN_COURS" }` (contrôleurs de Navinum).

| Code | Cas | Réessayable | Action conseillée |
|------|-----|:-----------:|-------------------|
| `RESEAU` | serveur injoignable, timeout | oui | `REESSAYER` |
| `SERVEUR` | erreur 5xx | oui | `REESSAYER` |
| `VALIDATION` | données invalides (400, 422) | non | `CORRIGER` |
| `NON_IDENTIFIE` | jeton visiteur absent ou invalide (401) | non | `SE_RECONNECTER` |
| `NON_AUTORISE` | action interdite (403) | non | `AUCUNE` |
| `INTROUVABLE` | ressource inexistante (404) | non | `RECHARGER` |
| `CONFLIT` | conflit non précisé (409) | oui | `ATTENDRE` |
| `LIEN_INVALIDE` | jeton de lancement inconnu | non | `RELANCER` |
| `LIEN_EXPIRE` | jeton de lancement expiré (410) | non | `RELANCER` |
| `DEJA_RATTACHE` | partie déjà rattachée à un autre visiteur | non | `RELANCER` |
| `SESSION_INTROUVABLE` | groupe (session) inexistant | non | `CHOISIR_AUTRE_GROUPE` |
| `SESSION_INVALIDE` | nom de groupe invalide | non | `CORRIGER` |
| `PARTIE_EN_COURS` | partie en cours dans le groupe | oui | `CHOISIR_AUTRE_GROUPE` |
| `PARTIE_DEMARREE` | la partie a déjà commencé | non | `AUCUNE` |
| `GROUPE_COMPLET` | groupe complet | non | `CHOISIR_AUTRE_GROUPE` |
| `NON_LEADER` | action réservée au leader | non | `AUCUNE` |
| `SCORE_REFUSE` | score non plausible (hors partie, saut de plus d'un point) | non | `AUCUNE` |
| `MEDAILLE_NON_ATTRIBUABLE` | médaille absente des `attribuables` de l'interactif | non | `AUCUNE` |
| `MEDAILLE_INCONNUE` | médaille inexistante | non | `AUCUNE` |
| `MODE_INDISPONIBLE` | mode de connexion pas encore disponible | non | `AUCUNE` |
| `INCONNU` | tout le reste | oui | `REESSAYER` |

Codes et actions : `NavinumAPI.erreurs.codes`, `NavinumAPI.erreurs.actions`.

### Classes d'erreurs disponibles

La librairie fournit une hiérarchie d'erreurs pour gérer les différents cas :

- `NavinumAPIError` - Erreur de base
- `NetworkError` - Problème réseau (timeout, connexion)
- `ValidationError` - Données invalides (HTTP 400)
- `UnauthorizedError` - Non autorisé (HTTP 401)
- `NotFoundError` - Ressource introuvable (HTTP 404)
- `GoneError` - Ressource expirée, ex. jeton de lancement (HTTP 410)
- `ServerError` - Erreur serveur (HTTP 500+)

Toutes portent `status` (code HTTP réel), `code` (code métier du serveur, ou null) et `body` (corps de la réponse).

### Accès aux classes d'erreur

```javascript
// Via la classe NavinumAPI
const { NotFoundError, NetworkError } = NavinumAPI;

// Ou via import (si module)
import {
    NavinumAPIError,
    NetworkError,
    ValidationError,
    UnauthorizedError,
    NotFoundError,
    GoneError,
    ServerError
} from '@navinum/api';
```

### Exemple de gestion d'erreurs

```javascript
try {
    const session = await api.sessions.join('global', {
        playerName: 'Alice'
    });
} catch (error) {
    if (error instanceof NavinumAPI.NetworkError) {
        // Problème de connexion
        alert('Vérifiez votre connexion internet');
    } else if (error instanceof NavinumAPI.NotFoundError) {
        // Session n'existe pas
        console.log('Session introuvable, création...');
        const newSession = await api.sessions.create({
            playerName: 'Alice',
            sessionId: 'global'
        });
    } else if (error instanceof NavinumAPI.ValidationError) {
        // Données invalides
        console.error('Données invalides:', error.details);
    } else if (error instanceof NavinumAPI.ServerError) {
        // Erreur serveur
        alert('Erreur serveur, réessayez plus tard');
    } else {
        // Autre erreur
        console.error('Erreur:', error.message);
    }
}
```

### Retry automatique

La librairie gère automatiquement les retry en cas d'erreur réseau :

```javascript
const api = new NavinumAPI({
    retryAttempts: 3,    // Nombre de tentatives
    retryDelay: 1000     // Délai entre tentatives (ms)
});

// En cas d'erreur réseau, la requête sera retentée 3 fois
// avec un délai de 1 seconde entre chaque tentative
```

---

## Configuration avancée

### Options de configuration

```javascript
const api = new NavinumAPI({
    // URL de base de l'API
    baseURL: 'http://localhost:8002',

    // Timeout des requêtes (ms)
    timeout: 10000,

    // Headers HTTP personnalisés
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Custom-Header': 'value'
    },

    // Mode debug (affiche les logs dans la console)
    debug: true,

    // Nombre de tentatives en cas d'erreur réseau
    retryAttempts: 3,

    // Délai entre les tentatives (ms)
    retryDelay: 2000,

    // Hub Mercure (temps réel) : relatif à baseURL ou absolu (défaut : /.well-known/mercure)
    mercureHub: '/.well-known/mercure',

    // Stockage du visiteur identifié (défaut : localStorage ; null : ne rien conserver)
    storage: localStorage
});
```

### Intercepteurs

#### Intercepteur de requête

```javascript
// Ajouter un token d'authentification
api.addRequestInterceptor((url, options) => {
    const token = localStorage.getItem('token');
    options.headers['Authorization'] = `Bearer ${token}`;
    return { url, options };
});

// Modifier l'URL
api.addRequestInterceptor((url, options) => {
    url += '?version=2';
    return { url, options };
});
```

#### Intercepteur de réponse

```javascript
// Logger toutes les réponses
api.addResponseInterceptor((response) => {
    console.log('API Response:', response.status, response.url);
    return response;
});

// Gérer le refresh token
api.addResponseInterceptor(async (response) => {
    if (response.status === 401) {
        // Token expiré, refresh
        await refreshToken();
    }
    return response;
});
```

### Configuration dynamique

```javascript
// Changer le baseURL
api.setBaseURL('https://production.navinum.com');

// Activer/désactiver le debug
api.setDebug(true);

// Accéder à la configuration
console.log(api.config.baseURL);
console.log(api.config.timeout);
```

---

## Exemples réels

### Exemple 1 : Quiz multi-joueur complet

```javascript
const api = new NavinumAPI({ debug: true });

// État de l'application
let sessionId = 'global';
let playerName = '';

// 1. Rejoindre ou créer la session
async function joinLobby(name) {
    playerName = name;

    try {
        // Essayer de rejoindre
        const result = await api.sessions.join(sessionId, {
            playerName: name
        });
        updatePlayersList(result.session.players);
    } catch (error) {
        if (error instanceof NavinumAPI.NotFoundError) {
            // Créer si n'existe pas
            const result = await api.sessions.create({
                playerName: name,
                sessionId: sessionId
            });
            updatePlayersList(result.session.players);
        }
    }
}

// 2. Polling pour les mises à jour
function startPolling() {
    setInterval(async () => {
        const data = await api.sessions.get(sessionId);

        // Mettre à jour l'UI
        updatePlayersList(data.session.players);

        // Quiz démarré ?
        if (data.session.status === 'playing') {
            startQuiz(data.session.theme);
        }
    }, 1000);
}

// 3. Démarrer le quiz
async function startQuiz(theme) {
    await api.sessions.start(sessionId, { theme });
}

// 4. Enregistrer une réponse
async function answerQuestion(isCorrect) {
    if (isCorrect) {
        const newScore = currentScore + 1;
        await api.sessions.updateScore(sessionId, {
            playerName: playerName,
            score: newScore
        });
    }
}

// 5. Déconnexion
async function disconnect() {
    await api.sessions.leave(sessionId, {
        playerName: playerName
    });
}
```

### Exemple 2 : Parcours avec RFID

```javascript
const api = new NavinumAPI({ debug: true });

// Flux complet d'une visite
async function startVisit(rfidTag) {
    try {
        // 1. Scanner le badge
        const scanResult = await api.rfid.scan(rfidTag);

        // 2. Récupérer ou créer le visiteur
        let visiteur;
        try {
            visiteur = await api.visiteurs.getByRfid(rfidTag);
        } catch (error) {
            // Premier scan, créer le visiteur
            visiteur = await api.visiteurs.register({
                nom: 'Anonyme',
                prenom: 'Visiteur',
                rfidTag: rfidTag
            });

            // Associer le tag au visiteur
            await api.rfid.associate(rfidTag, {
                resourceType: 'visiteur',
                resourceId: visiteur.id
            });
        }

        // 3. Logger le début de visite
        await api.logVisites.track({
            visiteurId: visiteur.id,
            action: 'start_visit',
            data: { timestamp: new Date().toISOString() }
        });

        // 4. Récupérer l'exposition active
        const exposition = await api.expositions.getActive();

        // 5. Récupérer les parcours disponibles
        const parcours = await api.parcours.getByExposition(exposition.id);

        // 6. Afficher les parcours au visiteur
        displayParcours(parcours);

        return { visiteur, exposition, parcours };

    } catch (error) {
        console.error('Erreur lors du démarrage de la visite:', error);
        throw error;
    }
}

// Sélection d'un parcours
async function selectParcours(visiteurId, parcoursId) {
    // Logger la sélection
    await api.logVisites.track({
        visiteurId: visiteurId,
        action: 'start_parcours',
        data: { parcoursId }
    });

    // Récupérer les interactifs du parcours
    const interactifs = await api.interactifs.getByParcours(parcoursId);

    return interactifs;
}

// Interaction avec un point d'intérêt
async function interactWithPoint(visiteurId, interactifId, data) {
    await api.interactifs.logInteraction(interactifId, {
        visiteurId: visiteurId,
        data: data
    });

    // Logger aussi dans les logs de visite
    await api.logVisites.track({
        visiteurId: visiteurId,
        action: 'view_interactif',
        data: {
            interactifId,
            ...data
        }
    });
}

// Fin de visite
async function endVisit(visiteurId) {
    await api.logVisites.track({
        visiteurId: visiteurId,
        action: 'end_visit',
        data: { timestamp: new Date().toISOString() }
    });

    // Récupérer les stats de la visite
    const logs = await api.logVisites.getByVisiteur(visiteurId);

    return generateVisitSummary(logs);
}
```

### Exemple 3 : Dashboard analytique

```javascript
const api = new NavinumAPI();

async function loadDashboard() {
    const today = new Date().toISOString().split('T')[0];
    const oneMonthAgo = new Date();
    oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

    // Récupérer les stats
    const stats = await api.logVisites.getStats({
        dateDebut: oneMonthAgo.toISOString().split('T')[0],
        dateFin: today
    });

    // Afficher les KPIs
    document.getElementById('totalVisiteurs').textContent = stats.totalVisiteurs;
    document.getElementById('totalInteractions').textContent = stats.totalActions;

    // Top interactifs
    const topInteractifs = stats.topInteractifs.slice(0, 5);
    displayChart(topInteractifs);

    // Récupérer toutes les expositions
    const expositions = await api.expositions.getAll();

    // Pour chaque exposition, récupérer ses parcours
    for (const expo of expositions) {
        const parcours = await api.parcours.getByExposition(expo.id);
        console.log(`${expo.nom}: ${parcours.length} parcours`);
    }
}
```

---

## Développement

### Scripts disponibles

```bash
# Build en mode développement (avec source maps)
npm run build:dev

# Build en mode production (minifié)
npm run build:prod

# Build les deux versions
npm run build

# Watch mode pour développement
npm run watch
```

### Structure du projet

```
navinum-api/
├── src/                        # Code source
│   ├── core/                   # Composants core
│   │   ├── ApiClient.js        # Client HTTP avec fetch
│   │   ├── Config.js           # Gestion configuration
│   │   ├── Errors.js           # Classes d'erreurs
│   │   └── Realtime.js         # Abonnements Mercure
│   ├── modules/                # Modules réutilisables (un dossier, un point d'entrée index.js)
│   │   ├── erreurs/            # NavinumAPI.erreurs
│   │   ├── auth/               # api.auth : Auth, ModeInvite (session), Stockage (appareil)
│   │   ├── gamification/       # api.gamification
│   │   └── multijoueur/        # api.multijoueur (Partie)
│   ├── react/                  # Hooks React facultatifs (NavinumAPI.creerHooksReact)
│   ├── resources/              # Appels HTTP par ressource du serveur (sans logique)
│   │   ├── BaseResource.js     # Classe de base (CRUD)
│   │   ├── SessionAPI.js       # API Sessions
│   │   ├── ExpositionAPI.js    # API Expositions
│   │   ├── ParcoursAPI.js      # API Parcours
│   │   ├── InteractifAPI.js    # API Interactifs
│   │   ├── VisiteurAPI.js      # API Visiteurs
│   │   ├── RfidAPI.js          # API RFID
│   │   ├── LogVisiteAPI.js     # API Logs
│   │   └── LaunchAPI.js        # API Lancement (jeton ?nv=)
│   └── index.js                # Point d'entrée principal
├── dist/                       # Builds générés
│   ├── navinum-api.js          # Version développement
│   └── navinum-api.min.js      # Version production
├── package.json
├── webpack.config.js
├── .babelrc
├── .gitignore
└── README.md
```

### Ajouter une nouvelle ressource API

1. Créer le fichier dans `src/resources/` :

```javascript
// src/resources/MonNouvelleAPI.js
import { BaseResource } from './BaseResource.js';

export class MonNouvelleAPI extends BaseResource {
    constructor(client) {
        super(client, 'mon_resource', { basePath: '/api/mon-api' });
    }

    // Ajouter vos méthodes spécifiques
    async maMethodeSpeciale(id, data) {
        return this.client.post(`${this.basePath}/${id}/special`, data);
    }
}
```

2. L'importer et l'ajouter dans `src/index.js` :

```javascript
import { MonNouvelleAPI } from './resources/MonNouvelleAPI.js';

export class NavinumAPI {
    constructor(options = {}) {
        // ...
        this.monNouvelle = new MonNouvelleAPI(this.client);
    }
}
```

3. Rebuild :

```bash
npm run build
```

### Tests

```bash
# Ouvrir le quiz de test
open http://localhost:8002/quiz/index.html

# Vérifier les logs dans la console
# avec debug: true
```

---

## Roadmap

- [ ] Publication sur npm
- [ ] Obfuscation du code en production
- [ ] Support TypeScript (.d.ts)
- [ ] Tests unitaires (Jest)
- [ ] Support des WebSockets pour temps réel
- [ ] Cache intelligent des requêtes
- [ ] Support offline avec IndexedDB

---

## Licence

Propriétaire - Navinum

---

## Contribuer

⚠️ **IMPORTANT** : À chaque modification de la librairie ou des API Symfony, la documentation DOIT être mise à jour.

### Checklist avant chaque commit

- [ ] Code testé et fonctionnel
- [ ] JSDoc ajoutées/mises à jour dans le code source
- [ ] README.md mis à jour (si ajout/modification de méthode)
- [ ] CHANGELOG.md mis à jour avec la version et les changements
- [ ] Exemples testés et fonctionnels
- [ ] Build réussi (`npm run build`)

### Fichiers à maintenir à jour

1. **Code source** (`src/`) - Avec JSDoc complètes
2. **README.md** - Documentation utilisateur avec exemples
3. **CHANGELOG.md** - Historique des versions
4. **CONTRIBUTING.md** - Guide détaillé de contribution

**📖 Voir [CONTRIBUTING.md](./CONTRIBUTING.md) pour le guide complet de contribution et de documentation.**

---

## Support

Pour toute question ou problème :
- Consulter [CONTRIBUTING.md](./CONTRIBUTING.md) pour les guidelines
- Consulter [CHANGELOG.md](./CHANGELOG.md) pour l'historique des versions
- Ouvrir une issue sur GitHub
- Contacter l'équipe technique Navinum
