# Contrat — lancement d'un interactif

## 1. Manifeste `navinum.json` (publié par l'interactif)

```json
{
  "libelle": "Quiz multi-joueurs",
  "version": "1.0.0",
  "editeur": "Navinum",
  "description": "…",
  "source_type": "html5",
  "url": "/quiz/index.html",
  "events": [{ "type": "quiz.completed", "description": "Payload : { score, total, … }" }],
  "configSchema": { "type": "object", "properties": { "tempsParQuestion": { "type": "integer", "default": 20 } } }
}
```
Import : admin → Interactifs → « Importer un manifeste », ou `bin/console app:interactif:import chemin/navinum.json`.

## 2. Lancement

Navinum ouvre `<url>?nv=<jeton>` (jeton : 48 caractères hexadécimaux, valable 12 h).

### `GET /api/launch/{jeton}` → 200
```json
{
  "interactif": { "id": "uuid", "libelle": "Quiz", "version": "1.0.0" },
  "parcours":   { "id": "uuid", "libelle": "…" } | null,
  "exposition": { "id": "uuid", "libelle": "…" } | null,
  "config":     { "…": "config effective = défauts du schéma ⊕ config du parcours" },
  "visiteur":   { "id": "uuid", "pseudo": "Alice", "anonyme": false } | null,
  "expiresAt":  "2026-11-22T06:00:00+00:00",
  "mercure":    { "hub": "https://…/.well-known/mercure", "topics": { "config": "/navinum/parcours-interactif/{uuid}/config" } } | null
}
```
404 jeton inconnu · 410 jeton expiré.

### `POST /api/launch/{jeton}/visiteur` → 200 (contexte à jour)
En-tête `Authorization: Bearer <jeton visiteur>` (obtenu par `POST /api/visiteur/invite` `{ "pseudo": "Alice" }`,
plus tard par le module d'authentification en ligne / RFID). Rattache le visiteur au lancement et au `LogVisite`
déjà commencé. 401 jeton visiteur invalide, 409 lancement déjà rattaché à un autre visiteur.

### `POST /api/launch/{jeton}/events` → 202
Corps : `{ "type": "quiz.completed", "payload": { "score": 8 } }` → `{ "accepted": true, "logVisite": "uuid" }`
- `payload.score` (entier) met à jour le score du `LogVisite` ;
- un type finissant par `.completed` ou `.ended` clôt la session ;
- 400 si `type` absent/invalide, `payload` non objet ou > 64 Ko.

## 3. Temps réel (Mercure, intégré à FrankenPHP)

| Topic | Contenu | Abonnés |
|---|---|---|
| `/navinum/parcours-interactif/{id}/config` | `{ "config": { … } }` à chaque enregistrement | l'interactif lancé (`ctx.onConfigChange`) |
| `/navinum/sessions/{id}` | `{ "type": "session", "session": { … } }` à chaque changement (lobby, démarrage, scores) | joueurs du quiz multijoueur |
| `/navinum/parcours/{id}/events` | `{ interactif, placement, type, score, session, at }` (sans identité visiteur) | page du parcours dans l'admin |

## 4. Côté interactif (librairie `@navinum/api`)

```js
const api = new NavinumAPI();
const ctx = await api.launch();           // lit ?nv= ; null si lancé hors Navinum
const config = ctx ? ctx.config : defaultsFromManifest;
await ctx?.track('quiz.completed', { score: 8 });
```
