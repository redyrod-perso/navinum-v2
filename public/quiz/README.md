# Quiz Interactif Multi-Joueurs

Application de quiz en temps réel permettant à plusieurs joueurs de s'affronter simultanément sur différents thèmes.

## 🎯 Fonctionnalités

- **Multi-joueurs en temps réel** : Plusieurs joueurs peuvent participer simultanément
- **Session globale** : Tous les joueurs rejoignent automatiquement la même session
- **Lobby dynamique** : Visualisation des joueurs connectés avant le démarrage
- **Thèmes variés** : Histoire, Sciences, Géographie
- **Scores en direct** : Suivi des scores de tous les joueurs en temps réel
- **Déconnexion propre** : Possibilité de quitter la session à tout moment

## 🏗️ Architecture

### Backend (Symfony)

Le backend ne gère **que les sessions**, toute la logique du quiz est côté client.

**Contrôleur** : `src/Controller/SessionController.php`
- Gestion des sessions (création, jonction, départ)
- Mise à jour des scores
- Stockage en fichiers JSON dans `/var/cache/sessions/`

### Frontend (React)

**Fichier principal** : `public/quiz/app.jsx`
- Chargement de la configuration (jeton Navinum `?nv=` ou valeurs par défaut du manifeste)
- Compte à rebours par question, nombre de questions et de joueurs configurables
- Gestion du jeu (questions, réponses, scores)
- Polling pour synchronisation multi-joueurs
- Interface utilisateur complète

### Manifeste et configuration

```
public/quiz/
├── navinum.json            # Manifeste : libellé, version, événements, configSchema (avec thèmes par défaut)
├── app.jsx                 # Application React
├── index.html              # Point d'entrée
├── test-api.html           # Page de test des API
└── README.md               # Ce fichier
```

Le quiz déclare ses paramètres dans `navinum.json` (`configSchema`, JSON Schema draft-07) :

| Propriété | Type | Défaut | Rôle |
|-----------|------|--------|------|
| `tempsParQuestion` | entier (s) | 20 | Temps pour répondre ; 0 = illimité. Temps écoulé = réponse fausse |
| `nbQuestions` | entier | 10 | Nombre de questions posées (les premières du thème) |
| `joueursMax` | entier | 8 | Nombre maximum de joueurs par groupe |
| `themes` | tableau | 3 thèmes | `{ id, libelle, questions: [{ question, reponses: [4 chaînes], correcte: 0-3 }] }` |

**Source de la configuration au démarrage :**

- **Lancé par Navinum** (`/quiz/index.html?nv=<token>`) : `api.launch()` récupère `ctx.config`
  (configuration du parcours, fusionnée côté serveur sur les défauts), fusionnée à nouveau
  sur les défauts du manifeste par sécurité. Le pseudo du visiteur est pré-rempli s'il est connu.
- **Mode autonome / dev** (sans `?nv=`) : le quiz charge `/quiz/navinum.json` et utilise les
  valeurs `default` du `configSchema`.
- **Mise à jour en direct** (avec `?nv=` et `ctx.mercure` non null) : le quiz s'abonne via
  `ctx.onConfigChange()`. Une configuration enregistrée dans l'admin est nettoyée, fusionnée sur
  les défauts du manifeste et appliquée **aux prochaines parties uniquement** (jamais pendant une
  partie en cours) ; un message discret « Configuration mise à jour » s'affiche sur l'accueil / le lobby.

### Événements émis (uniquement avec un jeton `?nv=`)

Envoyés via `ctx.track(type, payload)` (`POST /api/launch/{token}/events`) :

- `quiz.started` : `{ theme, nbQuestions, tempsParQuestion, joueurs, groupe }`
- `quiz.answered` : `{ theme, questionIndex, reponse, correcte, tempsReponse }`
- `quiz.completed` : `{ theme, score, total, gagnant, victoire, joueurs, duree }`
- `quiz.abandoned` : `{ theme, score, questionIndex, total }`

## 🚀 Démarrage

### Prérequis

- PHP 8.2+
- Symfony 7.3
- FrankenPHP
- Navigateur moderne supportant ES6+

### Lancer l'application

```bash
# Démarrer le serveur
make start

# Ou manuellement
frankenphp php-server --listen :8002 --root ./public
```

### Accéder au quiz

Ouvrir plusieurs onglets/navigateurs sur : `http://localhost:8002/quiz/`

## 📋 API Endpoints

Tous les endpoints sont préfixés par `/api/session`

### Gestion des sessions

#### `POST /api/session/create`
Créer une nouvelle session ou rejoindre une session existante

**Request:**
```json
{
  "playerName": "Alice",
  "sessionId": "global"  // optionnel
}
```

**Response:**
```json
{
  "sessionId": "global",
  "session": {
    "id": "global",
    "status": "lobby",
    "theme": null,
    "players": [
      {
        "name": "Alice",
        "score": 0,
        "joinedAt": 1234567890
      }
    ],
    "createdAt": 1234567890
  }
}
```

#### `POST /api/session/{sessionId}/join`
Rejoindre une session existante

**Request:**
```json
{
  "playerName": "Bob"
}
```

#### `GET /api/session/{sessionId}`
Récupérer les informations d'une session

**Response:**
```json
{
  "session": {
    "id": "global",
    "status": "playing",
    "theme": "histoire",
    "players": [...],
    "startedAt": 1234567890
  }
}
```

#### `POST /api/session/{sessionId}/start`
Démarrer le quiz pour tous les joueurs

**Request:**
```json
{
  "theme": "histoire"
}
```

#### `POST /api/session/{sessionId}/score`
Mettre à jour le score d'un joueur

**Request:**
```json
{
  "playerName": "Alice",
  "score": 5
}
```

#### `POST /api/session/{sessionId}/leave`
Quitter une session

**Request:**
```json
{
  "playerName": "Alice"
}
```

### Utilitaires

#### `POST /api/session/{sessionId}/reset`
Réinitialiser une session spécifique

#### `POST /api/sessions/clear`
Supprimer toutes les sessions

**Response:**
```json
{
  "status": "ok",
  "message": "Toutes les sessions ont été supprimées",
  "count": 1
}
```

## 🎮 Flux utilisateur

```
┌─────────────┐
│   Accueil   │  ← Saisie du nom
└──────┬──────┘
       │
       ↓
┌─────────────┐
│    Lobby    │  ← Visualisation des joueurs
│             │  ← Sélection du thème
└──────┬──────┘
       │
       ↓ (Démarrage par n'importe quel joueur)
┌─────────────┐
│     Jeu     │  ← Questions/Réponses
│             │  ← Scores en temps réel
└──────┬──────┘
       │
       ↓
┌─────────────┐
│  Résultats  │  ← Affichage du gagnant
└─────────────┘
```

## 🔄 Synchronisation multi-joueurs

Le système utilise un **polling** toutes les secondes pour synchroniser les états :

1. **Dans le lobby** : Mise à jour de la liste des joueurs
2. **Pendant le jeu** :
   - Détection du démarrage du quiz
   - Synchronisation des scores
   - Détection du gagnant

## 📝 Format des questions

Les thèmes et questions font partie de la configuration (`themes`) :

```json
{
  "id": "histoire",
  "libelle": "Histoire",
  "questions": [
    {
      "question": "En quelle année a eu lieu la Révolution française ?",
      "reponses": ["1789", "1792", "1804", "1815"],
      "correcte": 0
    }
  ]
}
```

`correcte` est l'index (0 à 3) de la bonne réponse dans `reponses`.

## ➕ Ajouter un nouveau thème

- **Pour un parcours** : modifier la configuration de l'interactif dans l'administration du parcours.
- **Par défaut (tous les parcours et mode autonome)** : ajouter le thème dans
  `configSchema.properties.themes.default` de `public/quiz/navinum.json`.

## 🧹 Commandes utiles

### Via Makefile

```bash
# Démarrer le serveur
make start

# Arrêter le serveur
make stop

# Redémarrer le serveur
make restart

# Voir le statut
make status

# Réinitialiser toutes les sessions (fichiers)
make sessions-reset

# Réinitialiser toutes les sessions (API)
make sessions-reset-api

# Nettoyer le cache complet
make clean
```

### Via cURL

```bash
# Réinitialiser toutes les sessions
curl -X POST http://localhost:8002/api/sessions/clear \
  -H "Content-Type: application/json"

# Réinitialiser la session globale
curl -X POST http://localhost:8002/api/session/global/reset \
  -H "Content-Type: application/json"
```

### Manuellement

```bash
# Supprimer toutes les sessions
rm -rf var/cache/sessions/*.json

# Créer le répertoire des sessions
mkdir -p var/cache/sessions
```

## 🛠️ Technologies utilisées

- **Backend** : Symfony 7.3, PHP 8.3+
- **Frontend** : React 18 (via CDN)
- **Serveur** : FrankenPHP
- **Transport** : HTTP REST + Polling
- **Stockage** : Fichiers JSON

## 🐛 Dépannage

### Les joueurs ne se voient pas

1. Vérifier que tous utilisent la même URL
2. Vérifier que le serveur est démarré : `make status`
3. Réinitialiser les sessions : `make sessions-reset-api`

### Le quiz ne démarre pas

1. Vérifier qu'un thème est sélectionné
2. Vérifier que la session est en statut "lobby"
3. Consulter la console navigateur (F12)

### Aucun thème / configuration introuvable

1. Vérifier que `/quiz/navinum.json` est accessible et valide (`python3 -m json.tool public/quiz/navinum.json`)
2. Avec `?nv=` : « Ce lien a expiré » = jeton expiré (410), « Lien de lancement invalide » = jeton inconnu (404)
3. Un thème sans question valide (4 réponses, `correcte` entre 0 et 3) est ignoré

## 📂 Structure du projet

```
/
├── src/
│   └── Controller/
│       └── SessionController.php    # Gestion des sessions
├── public/
│   └── quiz/
│       ├── index.html               # Point d'entrée
│       ├── app.jsx                  # Application React
│       ├── navinum.json             # Manifeste + configSchema (thèmes par défaut)
│       └── README.md                # Documentation
├── var/
│   └── cache/
│       └── sessions/                # Sessions JSON
└── Makefile                         # Commandes utiles
```

## 🔐 Sécurité

- Aucune authentification (application de démonstration)
- Sessions stockées en local (fichiers JSON)
- Noms de joueurs non validés
- Pas de limitation de rate

⚠️ **Ne pas utiliser en production sans sécurisation appropriée**

## 📄 License

Projet propriétaire - Navinum SpecKit

## 👥 Contributeurs

- Développé avec Claude Code

---

Pour toute question, consulter la documentation Symfony : https://symfony.com/doc
