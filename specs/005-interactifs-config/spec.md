# Spécification fonctionnelle : interactifs configurables par parcours

**Branche** : `005-interactifs-config`
**Créé le** : 2026-11-21
**Statut** : Implémenté (phases 1 à 3) — phases 4 et 5 à venir
**Remplace** : l'admin Sylius (specs 001 à 004), retirée en janvier 2026

## Contexte et vision

Navinum est une plateforme ouverte : n'importe qui doit pouvoir publier un interactif (jeu, quiz,
application) et des musées doivent pouvoir le proposer dans leurs parcours, chacun avec ses réglages.

Principes retenus :

1. **L'interactif décrit lui-même ses réglages.** Pas de formulaire codé par interactif dans Navinum.
   Le champ `Interactif.variable` contient un **JSON Schema** dont les `default` forment la configuration initiale.
2. **Un formulaire générique** est généré dans l'admin à partir de ce schéma.
3. **La configuration appartient au parcours** : `Exposition → Parcours → ParcoursInteractif (ordre + config) → Interactif`.
   Le même interactif peut être placé dans plusieurs parcours avec des réglages différents.
4. **La config est injectée par jeton** : Navinum ouvre `<url>?nv=<jeton>`, l'interactif appelle l'API.
5. **Les événements de l'interactif alimentent `LogVisite`**, point d'entrée de la gamification (XP, médailles, Rulerz).
6. **FrankenPHP exploité** : mode worker, hub Mercure intégré (temps réel), Vulcain.

## Rôles

| Rôle | Peut |
|---|---|
| `ROLE_ADMIN` | tout : catalogue d'interactifs, comptes, organisateurs, matériel (RFID, flottes, périphériques), visiteurs |
| `ROLE_ORGANISATEUR` | ses expositions (éditeur ou diffuseur = son organisateur), leurs parcours, la configuration et le test des interactifs placés |

Comptes stockés en base (`admin_user`), créés depuis l'admin ou par `bin/console app:user:create`.

## Histoires utilisateur

### HU1 — Publier un interactif (P1)
Un administrateur importe le manifeste `navinum.json` d'un interactif (ou le saisit) ; l'interactif apparaît au catalogue
avec son schéma de configuration.
- Étant donné un manifeste valide, quand il est importé, alors l'interactif est créé (ou mis à jour s'il porte le même libellé).
- Étant donné un `configSchema` qui n'est pas un objet JSON Schema, alors l'import est refusé avec un message.

### HU2 — Composer un parcours (P1)
Un organisateur ajoute des interactifs du catalogue à un parcours, les ordonne, les retire.
- L'ajout place l'interactif en fin de parcours avec la configuration par défaut.
- Monter / descendre réordonne ; retirer supprime le placement et sa configuration.

### HU3 — Configurer un interactif dans un parcours (P1)
L'organisateur ouvre « Configurer » : un formulaire généré depuis le schéma, prérempli avec la config effective.
- Une valeur hors schéma (type, bornes, enum, obligatoire) est refusée côté navigateur **et** côté serveur.
- « Réinitialiser » revient aux valeurs par défaut.
- Sans schéma, la config se saisit en JSON libre.
- À l'enregistrement, la nouvelle config est poussée en temps réel (Mercure) aux interactifs ouverts.

### HU4 — Lancer un interactif (P1)
« Tester » ouvre l'interactif comme un visiteur anonyme ; « Aperçu » (catalogue) l'ouvre avec ses valeurs par défaut.
- L'interactif reçoit sa config fusionnée (défauts ⊕ config du parcours) via `GET /api/launch/{jeton}`.
- Jeton inconnu → 404 ; expiré (12 h) → 410.

### HU5 — Suivre l'activité (P2)
Les événements (`POST /api/launch/{jeton}/events`) sont consignés dans un `LogVisite` (score, fin de session) et affichés
en direct sur la page du parcours.

### HU6 — Visiteurs identifiés et gamification (P2, phase 4 — à faire)
Un visiteur s'identifie **en ligne** (compte : email/mot de passe ou lien magique, puis fournisseurs externes) ou **au musée**
par badge **RFID** sur le réseau local. Un badge peut être rattaché à un compte. Le jeton de lancement porte alors le visiteur ;
ses événements donnent de l'XP et des médailles via Rulerz.

### HU7 — Ouverture aux développeurs tiers (P3, phase 5 — à faire)
Documentation publique du contrat (manifeste, API de lancement, événements) et interactif d'exemple minimal.

## Exigences

- **E1** `Interactif.variable` : JSON Schema (sous-ensemble draft-07 : type, properties, required, items, enum,
  minimum/maximum, minLength/maxLength, minItems/maxItems, default) ou, à défaut, JSON de valeurs simple.
- **E2** Config effective = fusion récursive : objets fusionnés clé par clé, listes et scalaires remplacés.
- **E3** Le jeton n'expose jamais l'identité civile du visiteur (id, pseudo, anonyme uniquement).
- **E4** Payload d'événement : objet JSON ≤ 64 Ko ; type `[a-z0-9_.-]{1,64}` ; 500 événements max conservés par session.
- **E5** Même configuration Docker en local et sur le VPS : un seul `Caddyfile`, les différences passent par des variables d'environnement.

## Phases

| Phase | Contenu | Statut |
|---|---|---|
| 1 | Comptes et rôles, admin Symfony pur (Bootstrap 5), CRUD expositions/parcours/visiteurs/matériel | ✅ |
| 2 | `ParcoursInteractif`, JSON Schema, formulaire générique, import de manifeste | ✅ |
| 3 | Jeton de lancement, API `/api/launch`, quiz branché, Mercure (événements + config à chaud) | ✅ |
| 4 | Visiteurs identifiés (en ligne + RFID), gamification | à faire |
| 5 | Documentation développeurs tiers, exemple | à faire |

Voir `data-model.md` et `contracts/launch-api.md`.
