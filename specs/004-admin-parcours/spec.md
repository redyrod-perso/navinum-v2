# Spécification Fonctionnelle : Administration des Parcours avec Sylius

**Branche Fonctionnalité** : `004-admin-parcours`  
**Créé le** : 2025-12-25  
**Statut** : Brouillon  
**Description** : "je veux qu'on fasse évoluer l'admin déjà existante avec sylius. Créer les parcours. On s'appuie sur navinum V1 pour avoir les memes champs dans l'admin (colones des tables, formulaires)"

## Scénarios Utilisateurs & Tests *(obligatoire)*

### Histoire Utilisateur 1 - Création de Parcours (Priorité : P1)

Un administrateur doit pouvoir créer des nouveaux parcours de visite pour organiser la progression des visiteurs dans les expositions. Cette fonctionnalité est essentielle car elle définit l'expérience de base des visiteurs.

**Pourquoi cette priorité** : Fondamental pour la plateforme muséale - sans parcours, impossible d'organiser les visites interactives.

**Test Indépendant** : Un administrateur peut se connecter, créer un nouveau parcours avec titre et ordre, et le voir apparaître dans la liste des parcours disponibles.

**Scénarios d'Acceptation** :

1. **Étant donné** un administrateur connecté à l'interface Sylius, **Quand** il accède à la section "Parcours" et clique "Créer un parcours", **Alors** un formulaire s'affiche avec les champs libellé, ordre et synchronisation
2. **Étant donné** un formulaire de création ouvert, **Quand** l'administrateur saisit un libellé unique et valide le formulaire, **Alors** le parcours est créé et affiché dans la liste avec un message de confirmation
3. **Étant donné** plusieurs parcours existants, **Quand** l'administrateur définit un ordre spécifique lors de la création, **Alors** le nouveau parcours s'insère à la position correcte dans la liste triée

---

### Histoire Utilisateur 2 - Gestion et Modification des Parcours (Priorité : P2)

Les administrateurs doivent pouvoir modifier les parcours existants, changer leur ordre d'affichage et les supprimer si nécessaire. Cette flexibilité est cruciale pour l'adaptation des expositions.

**Pourquoi cette priorité** : Permet l'évolution et la maintenance des parcours selon les besoins des expositions.

**Test Indépendant** : Un administrateur peut modifier le titre d'un parcours existant, réorganiser l'ordre des parcours, et supprimer un parcours non utilisé.

**Scénarios d'Acceptation** :

1. **Étant donné** des parcours existants dans la liste, **Quand** l'administrateur clique sur "Modifier" pour un parcours, **Alors** le formulaire de modification s'affiche avec les valeurs actuelles
2. **Étant donné** un formulaire de modification ouvert, **Quand** l'administrateur change l'ordre et sauvegarde, **Alors** la liste des parcours se réorganise selon le nouvel ordre
3. **Étant donné** un parcours sans relations actives, **Quand** l'administrateur clique "Supprimer" et confirme, **Alors** le parcours est supprimé de la liste avec un message de confirmation

---

### Histoire Utilisateur 3 - Association Parcours-Expositions (Priorité : P2)

Les administrateurs doivent pouvoir associer les parcours aux expositions appropriées pour organiser l'offre de visite selon les événements et thématiques.

**Pourquoi cette priorité** : Essentiel pour la gestion multi-expositions et la personnalisation des parcours.

**Test Indépendant** : Un administrateur peut créer des associations entre parcours et expositions, et voir ces relations dans l'interface de gestion.

**Scénarios d'Acceptation** :

1. **Étant donné** des parcours et expositions existants, **Quand** l'administrateur accède à la gestion des associations, **Alors** une interface permet de sélectionner les expositions pour chaque parcours
2. **Étant donné** un parcours sélectionné, **Quand** l'administrateur associe plusieurs expositions, **Alors** les relations sont sauvegardées et visibles dans la vue détail du parcours

---

### Histoire Utilisateur 4 - Association Parcours-Interactifs (Priorité : P3)

Les administrateurs peuvent organiser les interactifs au sein de chaque parcours pour définir la séquence d'activités proposées aux visiteurs.

**Pourquoi cette priorité** : Complète la gestion des parcours en définissant le contenu interactif proposé.

**Test Indépendant** : Un administrateur peut ajouter et réorganiser des interactifs dans un parcours, définissant ainsi l'expérience visiteur.

**Scénarios d'Acceptation** :

1. **Étant donné** un parcours et des interactifs disponibles, **Quand** l'administrateur accède à la gestion des interactifs du parcours, **Alors** il peut sélectionner et ordonner les interactifs souhaités
2. **Étant donné** des interactifs associés à un parcours, **Quand** l'administrateur modifie leur ordre, **Alors** la séquence est mise à jour et reflétée dans l'expérience visiteur

---

### Cas Particuliers

- Que se passe-t-il lorsqu'un administrateur tente de créer un parcours avec un libellé déjà existant ?
- Comment le système gère-t-il la suppression d'un parcours associé à des expositions actives ?
- Comment gérer les conflits d'ordre lorsque plusieurs parcours ont le même numéro d'ordre ?
- Que se passe-t-il lors de la modification simultanée d'un parcours par plusieurs administrateurs ?

## Exigences *(obligatoire)*

### Exigences Fonctionnelles

- **EF-001**: Le système DOIT permettre aux administrateurs de créer des nouveaux parcours avec libellé unique et ordre optionnel
- **EF-002**: Le système DOIT afficher la liste des parcours triée par ordre croissant avec possibilité de filtrage par statut
- **EF-003**: Les administrateurs DOIVENT pouvoir modifier tous les champs des parcours existants (libellé, ordre, synchronisation)
- **EF-004**: Le système DOIT permettre la suppression des parcours non associés à des expositions actives
- **EF-005**: Le système DOIT maintenir l'intégrité référentielle lors des associations parcours-expositions et parcours-interactifs
- **EF-006**: Le système DOIT conserver la compatibilité des champs avec Navinum V1 (libellé, ordre, is_tosync)
- **EF-007**: L'interface DOIT utiliser les composants et styles Sylius pour une cohérence visuelle avec l'admin existante
- **EF-008**: Le système DOIT générer des identifiants UUID pour tous les nouveaux parcours créés
- **EF-009**: Le système DOIT enregistrer automatiquement les horodatages de création et modification
- **EF-010**: Les administrateurs DOIVENT pouvoir gérer les associations plusieurs-à-plusieurs entre parcours et expositions
- **EF-011**: Le système DOIT permettre la gestion des relations entre parcours et interactifs avec ordonnancement
- **EF-012**: Le système DOIT valider l'unicité des libellés de parcours au niveau base de données et formulaire

### Entités Principales

- **Parcours** : Représente un itinéraire de visite avec libellé, ordre d'affichage, statut de synchronisation, relations avec expositions et interactifs
- **Exposition** : Événement muséal associé aux parcours pour organiser l'offre de visite
- **Interactif** : Contenu interactif intégré dans les parcours pour définir l'expérience visiteur

## Critères de Succès *(obligatoire)*

### Résultats Mesurables

- **CS-001**: Les administrateurs peuvent créer un nouveau parcours en moins de 30 secondes
- **CS-002**: L'interface affiche et gère correctement jusqu'à 100 parcours simultanés sans dégradation de performance
- **CS-003**: 100% des champs de Navinum V1 sont présents et fonctionnels dans la nouvelle interface Sylius
- **CS-004**: Les associations entre parcours et expositions se sauvegardent avec un taux de succès de 99,9%
- **CS-005**: Les administrateurs complètent la configuration d'un parcours complet (avec expositions et interactifs) en moins de 5 minutes
- **CS-006**: L'interface respecte 100% des conventions visuelles Sylius pour une intégration parfaite