# Spécifications du Backoffice d'Administration Navinum

## Vue d'ensemble

Le backoffice d'administration Navinum est une interface de gestion complète destinée aux administrateurs pour superviser et gérer l'ensemble des données et configurations du système de gestion d'expositions interactives.

## Objectifs principaux

1. **Gestion centralisée** : Fournir une interface unique pour administrer tous les aspects du système
2. **Contrôle des données** : Permettre la création, modification et suppression des entités métier
3. **Supervision** : Offrir des tableaux de bord et statistiques pour le monitoring
4. **Sécurité** : Assurer un contrôle d'accès strict avec authentification et autorisation

## Architecture technique

### Framework et technologies
- **Backend** : Symfony 6+ avec API Platform
- **Interface** : Twig + SyliusGridBundle pour les grilles de données
- **Base de données** : Doctrine ORM avec PostgreSQL/MySQL
- **Sécurité** : Composant Security de Symfony

### Structure des modules
Le backoffice est organisé autour des entités métier principales :
- Gestion des Expositions
- Gestion des Visiteurs
- Gestion des Interactifs
- Gestion des Flottes
- Gestion des Périphériques RFID
- Gestion des Parcours

## Spécifications fonctionnelles

### 1. Authentification et sécurité

#### 1.1 Connexion administrateur
- **Page de connexion** : `/admin/login`
- **Champs requis** : Username/Email + Mot de passe
- **Sécurisation** : Protection CSRF, limitation des tentatives
- **Session** : Gestion automatique avec timeout configurable

#### 1.2 Contrôle d'accès
- **Rôles** : ROLE_ADMIN, ROLE_SUPER_ADMIN
- **Permissions granulaires** : Lecture/Écriture par module
- **Restriction IP** : Optionnel, configuration par environnement

### 2. Tableau de bord principal

#### 2.1 Vue d'ensemble
- **URL** : `/admin/dashboard` ou `/admin/`
- **Widgets statistiques** :
  - Nombre total d'expositions actives
  - Nombre de visiteurs enregistrés
  - Nombre d'interactifs déployés
  - Activité récente (dernières visites)

#### 2.2 Navigation principale
- **Menu latéral** avec sections :
  - Tableau de bord
  - Expositions
  - Visiteurs
  - Interactifs
  - Flottes
  - Périphériques RFID
  - Parcours
  - Logs et statistiques

### 3. Gestion des Expositions

#### 3.1 Liste des expositions
- **URL** : `/admin/expositions`
- **Fonctionnalités** :
  - Liste paginée avec tri et filtres
  - Recherche par libellé
  - Filtres : Statut, Organisateur, Dates
  - Actions en lot : Activation/Désactivation

#### 3.2 Formulaire d'exposition
- **Création** : `/admin/expositions/new`
- **Modification** : `/admin/expositions/{id}/edit`
- **Champs** :
  - **Libellé*** (obligatoire, unique)
  - **Contexte** (sélection)
  - **Organisateur éditeur** (sélection)
  - **Organisateur diffuseur** (sélection)
  - **Synopsis** (texte long)
  - **Description** (texte long)
  - **Logo** (upload fichier)
  - **Publics cibles** (texte)
  - **Langues** (texte)
  - **URL illustration** (URL)
  - **URL studio** (URL)
  - **Date de début** (date)
  - **Date de fin** (date)
  - **Synchronisation** (checkbox)

#### 3.3 Associations
- **Parcours liés** : Interface de gestion des relations Many-to-Many
- **Prévisualisation** : Aperçu de l'exposition côté public

### 4. Gestion des Visiteurs

#### 4.1 Liste des visiteurs
- **URL** : `/admin/visiteurs`
- **Fonctionnalités** :
  - Liste paginée (100 par page)
  - Recherche : Email, Nom, Prénom, Pseudo
  - Filtres : Statut, Genre, CSP, Date d'inscription
  - Export CSV/Excel

#### 4.2 Fiche visiteur
- **Consultation** : `/admin/visiteurs/{id}`
- **Modification** : `/admin/visiteurs/{id}/edit`
- **Informations personnelles** :
  - Nom, Prénom
  - Email, Numéro mobile
  - Adresse complète
  - Date de naissance, Genre
  - CSP (Catégorie Socio-Professionnelle)
- **Préférences** :
  - Langue
  - Newsletter
  - Anonymisation
  - Réseaux sociaux (Facebook, Google, Twitter, etc.)
- **Historique** :
  - Visites réalisées
  - Interactifs utilisés
  - Scores et résultats

### 5. Gestion des Interactifs

#### 5.1 Catalogue des interactifs
- **URL** : `/admin/interactifs`
- **Vue grille** avec :
  - Miniature/Logo
  - Libellé et catégorie
  - Version et éditeur
  - Statut de déploiement
  - Date de dernière mise à jour

#### 5.2 Configuration d'interactif
- **Informations générales** :
  - Libellé, Description, Synopsis
  - Catégorie, Version, Éditeur
  - Publics, Langues, Marchés
- **Ressources** :
  - URLs des stores (iOS, Android, Windows)
  - Images (3 slots)
  - Fichiers interactifs
  - Pierre de Rosette (traductions)
- **Paramètres avancés** :
  - Variables de configuration
  - URL scheme
  - Types d'URL (interactif, visiteur)
  - Timestamps de début/fin
- **Options de logs** :
  - Log de visite requis
  - Log verbose requis
  - Parcours requis
  - Visiteur requis

### 6. Gestion des Flottes

#### 6.1 Organisation par exposition
- **URL** : `/admin/flottes`
- **Regroupement** par exposition
- **Informations** :
  - Libellé de la flotte
  - Exposition associée
  - Statut de synchronisation
- **Actions** :
  - Création/Suppression
  - Attribution à une exposition
  - Synchronisation forcée

### 7. Gestion des Périphériques RFID

#### 7.1 Inventaire RFID
- **URL** : `/admin/rfid`
- **Vue d'ensemble** :
  - Liste des tags RFID
  - Groupes RFID
  - Associations visiteurs-groupes
- **Fonctionnalités** :
  - Scan/Ajout de nouveaux tags
  - Attribution aux visiteurs
  - Gestion des groupes
  - Historique d'utilisation

### 8. Gestion des Parcours

#### 8.1 Conception de parcours
- **URL** : `/admin/parcours`
- **Éditeur graphique** :
  - Drag & drop d'interactifs
  - Définition de l'ordre
  - Branchements conditionnels
- **Configuration** :
  - Parcours par défaut
  - Règles de navigation
  - Points de contrôle

### 9. Logs et Monitoring

#### 9.1 Journaux système
- **URL** : `/admin/logs`
- **Types de logs** :
  - Logs de visite
  - Erreurs système
  - Actions administrateur
  - Synchronisations

#### 9.2 Statistiques d'usage
- **Tableaux de bord analytiques** :
  - Fréquentation par exposition
  - Utilisation des interactifs
  - Performances système
  - Rapports d'activité

### 10. Configuration système

#### 10.1 Paramètres globaux
- **URL** : `/admin/settings`
- **Sections** :
  - Paramètres généraux
  - Configuration email
  - Intégrations externes
  - Sauvegardes

## Spécifications techniques

### 1. Interface utilisateur

#### 1.1 Design System
- **Framework CSS** : Bootstrap 5+ ou Tailwind CSS
- **Composants** : Utilisation de SyliusGridBundle
- **Responsive** : Adaptation tablette et mobile
- **Thème** : Interface sobre et professionnelle

#### 1.2 UX/UI Guidelines
- **Navigation** : Menu latéral fixe avec breadcrumb
- **Feedback** : Messages flash pour les actions
- **Loading states** : Indicateurs de chargement
- **Validation** : Messages d'erreur contextuels

### 2. Performance

#### 2.1 Optimisations
- **Pagination** : Maximum 100 éléments par page
- **Cache** : Mise en cache des requêtes fréquentes
- **Lazy loading** : Chargement différé des images
- **Compression** : Assets minifiés en production

#### 2.2 Monitoring
- **Temps de réponse** : < 2 secondes pour les pages standard
- **Surveillance** : Logs d'erreur et métriques de performance

### 3. Sécurité

#### 3.1 Protection
- **CSRF** : Tokens sur tous les formulaires
- **XSS** : Échappement automatique des données
- **Injection SQL** : Utilisation exclusive de l'ORM Doctrine
- **Uploads** : Validation stricte des fichiers

#### 3.2 Audit
- **Logs d'accès** : Traçabilité des connexions
- **Actions sensibles** : Journalisation des modifications
- **Rétention** : Conservation des logs selon RGPD

## Roadmap d'implémentation

### Phase 1 : Infrastructure (Semaines 1-2)
- [ ] Configuration Symfony et bundles
- [ ] Authentification et autorisation
- [ ] Structure de base des templates
- [ ] Menu de navigation

### Phase 2 : Modules principaux (Semaines 3-6)
- [ ] CRUD Expositions
- [ ] CRUD Visiteurs  
- [ ] CRUD Interactifs
- [ ] Grilles de données avec SyliusGrid

### Phase 3 : Fonctionnalités avancées (Semaines 7-10)
- [ ] Gestion RFID et Flottes
- [ ] Système de logs
- [ ] Tableaux de bord analytiques
- [ ] Export de données

### Phase 4 : Finition (Semaines 11-12)
- [ ] Tests fonctionnels
- [ ] Optimisations performance
- [ ] Documentation utilisateur
- [ ] Déploiement et formation

## Maintenance et évolution

### Support continu
- **Mises à jour sécurité** : Patches mensuels
- **Nouvelles fonctionnalités** : Roadmap trimestrielle
- **Support utilisateur** : Documentation et formation

### Extensibilité
- **API REST** : Intégrations tierces
- **Webhooks** : Notifications externes
- **Plugins** : Architecture modulaire pour extensions

## Conclusion

Ce backoffice constitue le cœur opérationnel du système Navinum, permettant une gestion complète et efficace de tous les aspects des expositions interactives. L'architecture modulaire et la sécurité renforcée garantissent une évolution pérenne du système.