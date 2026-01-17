# Checklist des Exigences - Backoffice d'Administration Navinum

## ✅ Prérequis techniques

### Infrastructure
- [ ] Symfony 6+ installé et configuré
- [ ] API Platform installé
- [ ] SyliusGridBundle configuré  
- [ ] Base de données (PostgreSQL/MySQL) configurée
- [ ] Doctrine ORM configuré avec migrations
- [ ] Webpack Encore configuré pour les assets
- [ ] Environnements dev/test/prod configurés

### Sécurité
- [ ] Bundle Security configuré
- [ ] Authentification par formulaire configurée
- [ ] Système de rôles ROLE_ADMIN/ROLE_SUPER_ADMIN
- [ ] Protection CSRF activée
- [ ] Configuration HTTPS en production
- [ ] Firewall admin configuré
- [ ] Limitation des tentatives de connexion

## 📋 Fonctionnalités core

### Authentification
- [ ] Page de connexion `/admin/login`
- [ ] Formulaire avec username/email + mot de passe
- [ ] Redirection après connexion vers dashboard
- [ ] Déconnexion sécurisée
- [ ] Messages d'erreur de connexion
- [ ] Timeout de session configurable

### Tableau de bord
- [ ] Page dashboard `/admin/` et `/admin/dashboard`
- [ ] Widgets statistiques :
  - [ ] Nombre d'expositions actives
  - [ ] Nombre de visiteurs
  - [ ] Nombre d'interactifs
  - [ ] Activité récente
- [ ] Menu de navigation latéral
- [ ] Breadcrumb de navigation

## 🏛️ Gestion des Expositions

### Liste des expositions
- [ ] URL `/admin/expositions`
- [ ] Grille SyliusGrid avec pagination
- [ ] Colonnes : ID, Libellé, Organisateur, Dates, Statut
- [ ] Tri par colonnes
- [ ] Recherche par libellé
- [ ] Filtres : Statut, Organisateur, Dates
- [ ] Actions : Voir, Modifier, Supprimer
- [ ] Actions en lot : Activer/Désactiver

### Formulaires d'exposition
- [ ] Création `/admin/expositions/new`
- [ ] Modification `/admin/expositions/{id}/edit`
- [ ] Suppression avec confirmation
- [ ] Champs obligatoires validés :
  - [ ] Libellé (unique)
- [ ] Champs optionnels :
  - [ ] Contexte (relation)
  - [ ] Organisateur éditeur (relation)
  - [ ] Organisateur diffuseur (relation)
  - [ ] Synopsis (textarea)
  - [ ] Description (textarea)
  - [ ] Logo (upload)
  - [ ] Publics cibles
  - [ ] Langues
  - [ ] URL illustration
  - [ ] URL studio
  - [ ] Date début/fin
  - [ ] Checkbox synchronisation
- [ ] Validation côté serveur
- [ ] Messages de succès/erreur

## 👥 Gestion des Visiteurs

### Liste des visiteurs
- [ ] URL `/admin/visiteurs`
- [ ] Grille avec pagination (100/page)
- [ ] Colonnes : ID, Nom, Prénom, Email, Date inscription, Statut
- [ ] Recherche multi-champs : Email, Nom, Prénom, Pseudo
- [ ] Filtres :
  - [ ] Statut actif/inactif
  - [ ] Genre
  - [ ] CSP
  - [ ] Date d'inscription
- [ ] Export CSV/Excel
- [ ] Actions : Voir, Modifier, Désactiver

### Fiche visiteur
- [ ] Consultation `/admin/visiteurs/{id}`
- [ ] Modification `/admin/visiteurs/{id}/edit`
- [ ] Sections d'informations :
  - [ ] Identité : Nom, Prénom, Email, Mobile
  - [ ] Adresse : Rue, CP, Ville
  - [ ] Profil : Date naissance, Genre, CSP
  - [ ] Préférences : Langue, Newsletter, Anonyme
  - [ ] Réseaux sociaux : Facebook, Google, Twitter, etc.
- [ ] Historique :
  - [ ] Liste des visites
  - [ ] Interactifs utilisés
  - [ ] Scores obtenus
- [ ] Actions : Désactiver compte, Réinitialiser mot de passe

## 🎮 Gestion des Interactifs

### Catalogue des interactifs
- [ ] URL `/admin/interactifs`
- [ ] Vue grille avec cards
- [ ] Affichage : Logo, Libellé, Catégorie, Version, Statut
- [ ] Filtres : Catégorie, Éditeur, Statut
- [ ] Recherche par libellé
- [ ] Tri : Nom, Date, Popularité

### Configuration d'interactif
- [ ] Création `/admin/interactifs/new`
- [ ] Modification `/admin/interactifs/{id}/edit`
- [ ] Onglets de configuration :
  - [ ] **Général** : Libellé, Description, Catégorie, Version
  - [ ] **Éditeur** : Nom éditeur, Publics, Langues
  - [ ] **Distribution** : URLs stores (iOS/Android/Windows)
  - [ ] **Ressources** : 3 images, Fichiers, Pierre de Rosette
  - [ ] **Avancé** : Variables, URL scheme, Types URL
  - [ ] **Logs** : Options de journalisation
- [ ] Validation des URLs
- [ ] Upload d'images avec redimensionnement
- [ ] Prévisualisation des configurations

## 🚗 Gestion des Flottes

### Liste des flottes
- [ ] URL `/admin/flottes`
- [ ] Regroupement par exposition
- [ ] Colonnes : Libellé, Exposition, Statut sync
- [ ] Actions : Créer, Modifier, Supprimer, Synchroniser

### Gestion flotte
- [ ] Formulaire simple : Libellé + Exposition
- [ ] Validation unicité libellé
- [ ] Attribution automatique à exposition
- [ ] Synchronisation manuelle/automatique

## 📡 Gestion RFID

### Inventaire RFID
- [ ] URL `/admin/rfid`
- [ ] Onglets : Tags individuels, Groupes, Associations
- [ ] **Tags** :
  - [ ] Liste avec ID tag, Statut, Attribution
  - [ ] Scan nouveaux tags
  - [ ] Attribution à visiteur
- [ ] **Groupes** :
  - [ ] Création/modification groupes
  - [ ] Association tags à groupe
- [ ] **Visiteurs-Groupes** :
  - [ ] Attribution visiteurs aux groupes
  - [ ] Historique des associations

## 🗺️ Gestion des Parcours

### Éditeur de parcours
- [ ] URL `/admin/parcours`
- [ ] Interface drag & drop
- [ ] Sélection d'interactifs
- [ ] Définition de l'ordre
- [ ] Conditions de navigation
- [ ] Aperçu du parcours
- [ ] Sauvegarde/Publication

## 📊 Logs et Monitoring

### Journaux système
- [ ] URL `/admin/logs`
- [ ] Onglets par type :
  - [ ] Logs de visite
  - [ ] Erreurs système  
  - [ ] Actions admin
  - [ ] Synchronisations
- [ ] Filtres : Date, Niveau, Type, Utilisateur
- [ ] Pagination et recherche
- [ ] Export des logs

### Statistiques
- [ ] Tableaux de bord analytiques
- [ ] Graphiques fréquentation par exposition
- [ ] Métriques d'utilisation des interactifs
- [ ] Rapports d'activité exportables
- [ ] Alertes automatiques

## ⚙️ Configuration système

### Paramètres globaux
- [ ] URL `/admin/settings`
- [ ] Sections configuration :
  - [ ] Paramètres généraux
  - [ ] Configuration email (SMTP)
  - [ ] Intégrations externes
  - [ ] Sauvegardes automatiques
- [ ] Validation des paramètres
- [ ] Test de connexion (email, API)

## 🎨 Interface utilisateur

### Design et UX
- [ ] Framework CSS (Bootstrap/Tailwind) intégré
- [ ] Template de base avec menu latéral
- [ ] Responsive design (mobile/tablette)
- [ ] Messages flash pour feedback
- [ ] Loading states sur actions
- [ ] Confirmation pour suppressions
- [ ] Breadcrumb navigation
- [ ] Search highlights
- [ ] Pagination intuitive

### Composants réutilisables
- [ ] Card component
- [ ] Modal confirmations  
- [ ] Datatable avec SyliusGrid
- [ ] Form widgets personnalisés
- [ ] Upload component
- [ ] Date/time pickers

## 🔒 Sécurité

### Protection
- [ ] Tokens CSRF sur tous formulaires
- [ ] Échappement XSS automatique
- [ ] Validation uploads (type, taille)
- [ ] Sanitisation des inputs
- [ ] Rate limiting connexions
- [ ] Headers sécurité (HSTS, CSP)

### Audit et logs
- [ ] Log toutes connexions admin
- [ ] Journalisation actions sensibles
- [ ] Historique modifications entités
- [ ] Alertes sécurité automatiques
- [ ] Rétention logs conforme RGPD

## 🚀 Performance

### Optimisations
- [ ] Pagination par défaut (50-100 items)
- [ ] Cache requêtes fréquentes
- [ ] Lazy loading images
- [ ] Assets minifiés/compressés
- [ ] CDN pour fichiers statiques
- [ ] Optimisation requêtes Doctrine

### Monitoring
- [ ] Métriques temps réponse
- [ ] Monitoring erreurs (Sentry)
- [ ] Profiler Symfony en dev
- [ ] Logs performance en prod

## 📚 Documentation et tests

### Documentation
- [ ] README installation
- [ ] Guide utilisateur admin
- [ ] Documentation API
- [ ] Schémas base de données
- [ ] Guide troubleshooting

### Tests
- [ ] Tests unitaires entités
- [ ] Tests fonctionnels controllers
- [ ] Tests intégration formulaires
- [ ] Tests sécurité accès
- [ ] Tests performance critiques

## 🚀 Déploiement

### Environnements
- [ ] Configuration dev/test/prod
- [ ] Variables environnement sécurisées
- [ ] Migrations automatiques
- [ ] Scripts déploiement
- [ ] Rollback procedures

### Monitoring production
- [ ] Health checks
- [ ] Alerting automatique  
- [ ] Backup automatique
- [ ] Monitoring ressources serveur