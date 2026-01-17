# Plan d'Implémentation : Administration des Parcours avec Sylius

**Branche** : `004-admin-parcours` | **Date** : 2025-12-25 | **Spec** : [spec.md](spec.md)
**Input** : Spécification fonctionnelle de `/specs/004-admin-parcours/spec.md`

## Résumé

Évolution de l'administration existante avec Sylius pour créer une interface complète de gestion des parcours muséaux. L'approche technique s'appuie sur l'architecture Symfony 7 existante en intégrant les composants Sylius pour l'administration, tout en maintenant la compatibilité des champs avec Navinum V1.

## Contexte Technique

**Langage/Version** : PHP 8.3+ avec Symfony 7  
**Dépendances Principales** : Sylius Bundle, Doctrine ORM, API Platform, Twig  
**Stockage** : Base de données existante avec entités Doctrine (Parcours, Exposition, Interactif)  
**Tests** : PHPUnit pour les tests unitaires et fonctionnels  
**Plateforme Cible** : Serveur web avec FrankenPHP
**Type de Projet** : Web application avec interface d'administration  
**Objectifs de Performance** : Interface responsive <2s, gestion de 100 parcours simultanés  
**Contraintes** : Compatibilité obligatoire avec schéma Navinum V1, intégration Sylius native  
**Échelle/Portée** : Extension de l'admin existante, ~10 nouvelles vues, CRUD complet parcours

## Vérification Constitution

*GATE: Doit passer avant la recherche Phase 0. Re-vérification après la conception Phase 1.*

### Conformité aux Principes Fondamentaux

✅ **I. Architecture Moderne et Migration Progressive**
- Utilisation de Symfony 7 avec composants modernes ✓
- Serveur FrankenPHP pour performances optimales ✓ 
- Intégration native Doctrine ORM ✓

✅ **II. Services REST Prioritaires et Communication Interactifs** 
- API Platform maintenu pour compatibilité ✓
- Format JSON pour interopérabilité ✓

✅ **III. Migration par Entités Métier**
- Focus sur entité Parcours respectant l'ordre de migration ✓
- Compatibilité données V1 pendant transition ✓

✅ **IV. Préservation des Données et Synchronisation**
- Maintien du champ `is_tosync` ✓
- Structure entités compatible Navinum V1 ✓

✅ **V. Sécurité et Performance Muséale**
- Interface d'administration Symfony Security ✓
- Optimisation pour environnements muséaux ✓

### Conformité Processus de Développement

✅ **Stack Technique Validée**
- Symfony 7 + Sylius Bundle ✓
- Doctrine ORM + API Platform ✓
- Tests PHPUnit obligatoires ✓

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
# [REMOVE IF UNUSED] Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# [REMOVE IF UNUSED] Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# [REMOVE IF UNUSED] Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure: feature modules, UI flows, platform tests]
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
