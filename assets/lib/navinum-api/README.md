# Navinum API Client

Librairie JavaScript complète pour interagir avec les APIs Symfony de Navinum. Supporte tous les interactifs (quiz, jeux, parcours, etc.) avec gestion des erreurs, retry automatique et mode debug.

---

## Table des matières

- [Installation](#installation)
- [Quick Start](#quick-start)
- [API Usage](#api-usage)
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

#### Récupérer l'état de la session (Polling)

```javascript
const data = await api.sessions.get('global');
console.log('Joueurs:', data.session.players);
console.log('Status:', data.session.status);

// Polling automatique toutes les secondes
setInterval(async () => {
    const updated = await api.sessions.get('global');
    updateUI(updated.session);
}, 1000);
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

#### Enregistrer un nouveau visiteur

```javascript
const visiteur = await api.visiteurs.register({
    nom: 'Dupont',
    prenom: 'Jean',
    email: 'jean.dupont@example.com',
    telephone: '0612345678',
    csp: 'etudiant',
    age: 25
});

console.log('Visiteur créé, ID:', visiteur.id);
```

#### Rechercher un visiteur par RFID

```javascript
const visiteur = await api.visiteurs.getByRfid('TAG123456');

if (visiteur) {
    console.log('Bonjour', visiteur.prenom, visiteur.nom);
}
```

#### CRUD standard

```javascript
// Liste
const visiteurs = await api.visiteurs.getAll();

// Par ID
const v = await api.visiteurs.getById(123);

// Update
await api.visiteurs.update(123, {
    email: 'nouveau@email.com'
});
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

## Gestion des erreurs

### Classes d'erreurs disponibles

La librairie fournit une hiérarchie d'erreurs pour gérer les différents cas :

- `NavinumAPIError` - Erreur de base
- `NetworkError` - Problème réseau (timeout, connexion)
- `ValidationError` - Données invalides (HTTP 400)
- `UnauthorizedError` - Non autorisé (HTTP 401)
- `NotFoundError` - Ressource introuvable (HTTP 404)
- `ServerError` - Erreur serveur (HTTP 500+)

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
    retryDelay: 2000
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
│   │   └── Errors.js           # Classes d'erreurs
│   ├── resources/              # APIs par ressource
│   │   ├── BaseResource.js     # Classe de base (CRUD)
│   │   ├── SessionAPI.js       # API Sessions
│   │   ├── ExpositionAPI.js    # API Expositions
│   │   ├── ParcoursAPI.js      # API Parcours
│   │   ├── InteractifAPI.js    # API Interactifs
│   │   ├── VisiteurAPI.js      # API Visiteurs
│   │   ├── RfidAPI.js          # API RFID
│   │   └── LogVisiteAPI.js     # API Logs
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
