# Rafraîchissement temps réel des groupes avec Mercure

## Fonctionnement

Quand un utilisateur **crée un nouveau groupe RFID** via `POST /api/rfid_groupes`, tous les utilisateurs connectés sur la **page de bienvenue** du quiz voient leur **liste déroulante se rafraîchir automatiquement**.

## Architecture

### Backend (Symfony)

**1. RfidGroupeProcessor** (`src/State/Processor/RfidGroupeProcessor.php`)
- Intercepte les opérations POST/PUT/DELETE sur les groupes RFID
- Publie un événement Mercure sur le topic `rfid-groupes` après création
- Format du message :
```json
{
    "type": "groupe_created",
    "groupe": {
        "id": "uuid",
        "nom": "nom du groupe"
    }
}
```

**2. Configuration de l'entité**
```php
#[ApiResource(
    operations: [
        new Post(
            processor: RfidGroupeProcessor::class  // ✅ Publie sur Mercure
        ),
    ]
)]
```

### Frontend (React)

**1. Écoute Mercure uniquement sur la page d'accueil**
```javascript
useEffect(() => {
    if (stage !== 'home') return;

    const mercureUrl = new URL('http://localhost:3000/.well-known/mercure');
    mercureUrl.searchParams.append('topic', 'rfid-groupes');

    const eventSource = new EventSource(mercureUrl);

    eventSource.onmessage = (event) => {
        const data = JSON.parse(event.data);
        if (data.type === 'groupe_created') {
            loadGroups(); // ✅ Rafraîchir la liste
        }
    };

    return () => eventSource.close();
}, [stage]);
```

## Test avec 2 navigateurs

### Navigateur 1 (Alice)
1. Ouvrir http://localhost/quiz/index.html
2. **NE PAS encore remplir le formulaire**
3. Laisser ouvert sur la page de bienvenue

### Navigateur 2 (Bob)
1. Ouvrir http://localhost/quiz/index.html
2. Entrer nom : "Bob"
3. Mode : **"Créer un groupe"**
4. Nom du groupe : **"TestMercure2024"**
5. Cliquer sur **"Rejoindre le lobby"**

### Résultat attendu

**Dans le Navigateur 1 (Alice) :**
- ✅ La liste déroulante se rafraîchit **automatiquement**
- ✅ "TestMercure2024" apparaît dans la liste
- ✅ **Sans recharger la page !**

**Dans le Navigateur 2 (Bob) :**
- Bob est dans le lobby normalement

## Vérification

### 1. Vérifier que Mercure fonctionne
```bash
# Vérifier que le conteneur Mercure tourne
docker-compose ps mercure

# Tester l'accès à Mercure
curl http://localhost:3000/.well-known/mercure
```

### 2. Vérifier la publication des événements

**Console navigateur (F12) :**
```javascript
// Devrait afficher quand un groupe est créé
Nouveau groupe créé: {id: "...", nom: "TestMercure2024"}
```

### 3. Vérifier les logs backend
```bash
docker-compose logs -f app | grep Mercure
```

## Configuration Mercure

### Variables d'environnement (.env)
```bash
MERCURE_URL=http://mercure:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET="dev_mercure_secret_2024"
```

### Configuration du Hub Mercure
```yaml
# docker-compose.yaml
mercure:
  image: dunglas/mercure
  environment:
    SERVER_NAME: ':3000'
    MERCURE_PUBLISHER_JWT_KEY: ${MERCURE_JWT_SECRET}
    MERCURE_SUBSCRIBER_JWT_KEY: ${MERCURE_JWT_SECRET}
    MERCURE_EXTRA_DIRECTIVES: |
      anonymous
      cors_origins *
```

## Dépannage

### La liste ne se rafraîchit pas

**1. Vérifier la connexion Mercure dans la console :**
```javascript
// Devrait s'afficher dans la console
EventSource connected to Mercure
```

**2. Vérifier que le POST crée bien le groupe :**
```bash
curl -X POST http://localhost/api/rfid_groupes \
  -H "Content-Type: application/ld+json" \
  -d '{"nom":"TestAPI"}'
```

**3. Vérifier les logs Mercure :**
```bash
docker-compose logs mercure --tail 50
```

**4. Tester Mercure manuellement :**
```bash
# Terminal 1 : S'abonner
curl -N http://localhost:3000/.well-known/mercure?topic=rfid-groupes

# Terminal 2 : Publier (nécessite JWT)
curl -X POST http://localhost:3000/.well-known/mercure \
  -d "topic=rfid-groupes" \
  -d "data=test"
```

### Erreur CORS

Si vous voyez une erreur CORS dans la console :
```bash
# Vérifier la configuration Mercure
docker-compose exec mercure env | grep CORS
```

Devrait contenir :
```
cors_origins *
```

### Le groupe apparaît en double

Cela peut arriver si :
1. L'API `/api/rfid_groupes` retourne déjà le nouveau groupe
2. Mercure déclenche un second rechargement

**Solution :** Filtrer les doublons côté frontend ou utiliser un Set.

## Flux complet

```
1. Bob crée "TestMercure2024"
   └→ POST /api/rfid_groupes

2. RfidGroupeProcessor intercepte
   └→ Persiste en BDD
   └→ Publie sur Mercure topic='rfid-groupes'

3. Mercure diffuse à tous les abonnés
   └→ Alice (sur page home) reçoit l'événement

4. EventSource onmessage déclenché
   └→ loadGroups() appelé
   └→ Fetch /api/rfid_groupes

5. Liste déroulante mise à jour
   └→ "TestMercure2024" apparaît ✅
```

## Prochaines améliorations

1. **Supprimer en temps réel** : Publier aussi sur DELETE
2. **Modifier en temps réel** : Publier sur PUT
3. **Optimisation** : Envoyer directement le nouveau groupe au lieu de refetch
4. **Filtrage doublons** : Utiliser un Set pour éviter les doublons
5. **Notification visuelle** : Badge "Nouveau groupe !" temporaire
