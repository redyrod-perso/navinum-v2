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
- Chargement des thèmes et questions
- Gestion du jeu (questions, réponses, scores)
- Polling pour synchronisation multi-joueurs
- Interface utilisateur complète

### Données statiques

```
public/quiz/
├── themes.json              # Liste des thèmes disponibles
├── questions/
│   ├── histoire.txt        # Questions d'histoire
│   ├── sciences.txt        # Questions de sciences
│   └── geographie.txt      # Questions de géographie
├── app.jsx                 # Application React
├── index.html              # Point d'entrée
└── README.md               # Ce fichier
```

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

Les questions sont stockées dans des fichiers `.txt` avec le format suivant :

```
Question|Réponse1|Réponse2|Réponse3|Réponse4|IndexCorrect
```

**Exemple** (`questions/histoire.txt`):
```
En quelle année a eu lieu la Révolution française ?|1789|1792|1804|1815|0
Qui était le premier empereur romain ?|Jules César|Auguste|Néron|Caligula|1
```

## ➕ Ajouter un nouveau thème

1. Créer le fichier de questions :
```bash
touch public/quiz/questions/mon-theme.txt
```

2. Ajouter les questions au format spécifié

3. Référencer le thème dans `themes.json` :
```json
["histoire", "sciences", "geographie", "mon-theme"]
```

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

### Erreur 404 sur les questions

1. Vérifier que le fichier existe dans `public/quiz/questions/`
2. Vérifier les permissions : `chmod 644 public/quiz/questions/*.txt`
3. Vérifier que le thème est bien dans `themes.json`

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
│       ├── themes.json              # Liste des thèmes
│       ├── questions/               # Fichiers de questions
│       │   ├── histoire.txt
│       │   ├── sciences.txt
│       │   └── geographie.txt
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
