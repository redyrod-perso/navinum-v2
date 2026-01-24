# Quiz Multi-joueur avec Groupes RFID

## Fonctionnalités

### Système de Leader
- **Le premier joueur** qui crée ou rejoint un groupe devient automatiquement le **leader** 👑
- **Seul le leader** peut :
  - Choisir le thème du quiz
  - Démarrer le quiz
- **Tous les joueurs** du groupe :
  - Voient le lobby en temps réel
  - Sont redirigés automatiquement vers le quiz quand le leader démarre
  - Jouent le même quiz en même temps

### Groupes RFID
- Les groupes sont liés aux `rfid_groupe` de la base de données
- Un groupe peut être :
  - **Sélectionné** parmi les groupes existants
  - **Créé** avec un nouveau nom
- Tous les joueurs du même groupe sont synchronisés

## Guide de test

### Test avec 2 navigateurs/onglets

#### Onglet 1 (Leader)
1. Ouvrir http://localhost/quiz/index.html
2. Entrer nom : "Alice"
3. Mode : **"Créer un groupe"**
4. Nom du groupe : "TestGroup2024"
5. Cliquer sur **"Rejoindre le lobby"**

**Résultat attendu :**
- Vous êtes dans le lobby
- Votre nom apparaît avec l'icône 👑 et le badge "Leader"
- Vous voyez les boutons de sélection de thème
- Le bouton "Démarrer le Quiz" est visible

#### Onglet 2 (Membre)
1. Ouvrir http://localhost/quiz/index.html (nouvel onglet/fenêtre)
2. Entrer nom : "Bob"
3. Mode : **"Sélectionner un groupe"**
4. Sélectionner dans la liste : **"TestGroup2024"**
5. Cliquer sur **"Rejoindre le lobby"**

**Résultat attendu :**
- Vous êtes dans le lobby
- Vous voyez Alice avec 👑 et "Leader"
- Votre nom apparaît avec 👤
- Vous voyez le message "⏳ En attente du leader..."
- Pas de contrôles de thème ni de bouton démarrer

#### Onglet 1 (Leader) - Suite
1. Sélectionner un thème (ex: "nature")
2. Cliquer sur **"Démarrer le Quiz"**

**Résultat attendu :**
- Le quiz démarre immédiatement pour Alice
- **Bob est automatiquement redirigé** vers la première question
- Les deux joueurs voient la même question
- Les scores sont mis à jour en temps réel

## Architecture Backend

### Modifications apportées

#### SessionController.php

**Champs ajoutés à la session :**
```php
[
    'id' => $sessionId,
    'rfidGroupeName' => $rfidGroupeName,
    'leader' => $playerName,  // ✅ NOUVEAU
    'status' => 'lobby',
    'theme' => null,
    'players' => [
        [
            'name' => $playerName,
            'score' => 0,
            'joinedAt' => time(),
            'isLeader' => true  // ✅ NOUVEAU
        ]
    ],
    'createdAt' => time(),
    'lastUpdate' => time()
]
```

**Endpoint modifié : POST /api/session/{sessionId}/start**
```php
// Vérifie maintenant que le joueur est bien le leader
if ($session['leader'] !== $playerName) {
    return 403 Forbidden
}
```

## Architecture Frontend

### Modifications app.jsx

**Détection du leader :**
```javascript
const currentPlayer = players.find(p => p.name === playerName);
const isLeader = currentPlayer?.isLeader || false;
```

**Affichage conditionnel :**
```javascript
{isLeader ? (
    // Afficher sélection thème + bouton démarrer
) : (
    // Afficher message d'attente
)}
```

**Icônes dans la liste des joueurs :**
- 👑 = Leader
- 👤 = Membre

## API Endpoints

### Créer/Rejoindre une session
```bash
POST /api/session/create
{
    "playerName": "Alice",
    "sessionId": "TestGroup2024",
    "rfidGroupeName": "TestGroup2024"
}

Response:
{
    "sessionId": "TestGroup2024",
    "session": {
        "leader": "Alice",  # ✅ Qui peut démarrer
        "players": [
            {
                "name": "Alice",
                "isLeader": true  # ✅ Badge UI
            }
        ]
    }
}
```

### Démarrer le quiz (Leader uniquement)
```bash
POST /api/session/{sessionId}/start
{
    "theme": "nature",
    "playerName": "Alice"  # ✅ NOUVEAU : vérification leader
}

Response (si non-leader):
{
    "error": "Seul le leader du groupe peut démarrer le quiz"
}
```

## Synchronisation temps réel

### Server-Sent Events (SSE)
```javascript
GET /api/session/{sessionId}/stream
```

**Events envoyés :**
1. Nouvel joueur rejoint → tous reçoivent la liste mise à jour
2. Leader choisit thème → tous voient le thème
3. Leader démarre → tous redirigés vers `/playing`
4. Score mis à jour → tous voient les scores en temps réel

## Troubleshooting

### "Seul le leader peut démarrer"
- Vérifier que vous êtes bien le premier à avoir créé le groupe
- Vérifier que votre badge affiche "Leader" dans la liste

### Les joueurs ne voient pas les mises à jour
- Vérifier la connexion SSE dans la console navigateur
- Vérifier les logs : `docker-compose logs -f app`

### Groupe n'apparaît pas dans la liste
- L'API `/api/rfid_groupes` doit retourner les groupes
- Vérifier : `curl http://localhost/api/rfid_groupes`
- Créer manuellement : Mode "Créer un groupe"

## Prochaines améliorations possibles

1. **Mercure Hub** : Remplacer SSE par Mercure pour scalabilité
2. **Transfert de leadership** : Permettre de passer le rôle de leader
3. **Kick de joueurs** : Le leader peut retirer des joueurs
4. **Reconnexion** : Gérer les déconnexions/reconnexions
5. **Historique des parties** : Sauvegarder les résultats en BDD
