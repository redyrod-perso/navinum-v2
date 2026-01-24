# Guide de déploiement Navinum

## Configuration Production

### 1. Préparer les variables d'environnement

Copiez et configurez le fichier `.env.prod` :

```bash
cp .env.prod .env.prod.backup
```

**Variables importantes à configurer :**

- `APP_SECRET` : Secret Symfony (générer avec `php bin/console secrets:generate-keys`)
- `POSTGRES_PASSWORD` : Mot de passe PostgreSQL sécurisé
- `MERCURE_JWT_SECRET` : Secret JWT pour Mercure
- `SERVER_NAME` : Votre nom de domaine (ex: srv802003.hstgr.cloud)

### 2. Déploiement initial

```bash
# Arrêter les services existants si nécessaire
docker-compose down

# Construire les images
docker-compose build --build-arg APP_ENV=prod

# Démarrer les services
docker-compose up -d

# Attendre que la base soit prête
sleep 10

# Exécuter les migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Vider le cache
docker-compose exec app php bin/console cache:clear

# Préchauffer le cache
docker-compose exec app php bin/console cache:warmup
```

### 3. Vérification

```bash
# Vérifier l'état des services
docker-compose ps

# Vérifier les logs
docker-compose logs -f app

# Tester l'accès
curl http://localhost/
```

## Migrations

### Migration initiale (Version20260124173303)

Cette migration crée toutes les tables PostgreSQL :

- **Tables principales** : contexte, csp, exposition, interactif, langue, parcours, visiteur, etc.
- **Tables de liaison** : exposition_parcours, parcours_interactif, etc.
- **Tables système** : messenger_messages, sync_log, delete_log

**Caractéristiques :**
- ✅ Syntaxe PostgreSQL native (UUID, BOOLEAN, TIMESTAMP)
- ✅ 268 requêtes SQL
- ✅ Contraintes et index optimisés
- ✅ Support Doctrine Messenger

### Appliquer la migration en production

```bash
# Voir l'état actuel
docker-compose exec app php bin/console doctrine:migrations:status

# Appliquer les migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Vérifier la synchronisation
docker-compose exec app php bin/console doctrine:schema:validate
```

### Rollback (si nécessaire)

```bash
# Revenir à la version précédente
docker-compose exec app php bin/console doctrine:migrations:migrate prev --no-interaction
```

## Mise à jour de production

```bash
# 1. Récupérer les derniers changements
git pull origin main

# 2. Reconstruire si Dockerfile modifié
docker-compose build

# 3. Redémarrer les services
docker-compose up -d

# 4. Appliquer les nouvelles migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# 5. Vider le cache
docker-compose exec app php bin/console cache:clear
```

## Commandes utiles

```bash
# Accéder au conteneur
docker-compose exec app bash

# Voir les logs en temps réel
docker-compose logs -f app

# Redémarrer un service
docker-compose restart app

# Arrêter tous les services
docker-compose down

# Sauvegarder la base de données
docker-compose exec database pg_dump -U navinum navinum > backup_$(date +%Y%m%d).sql

# Restaurer la base de données
docker-compose exec -T database psql -U navinum navinum < backup.sql
```

## Troubleshooting

### Erreur "role does not exist"

Solution : Supprimer le volume et recréer :
```bash
docker-compose down
docker volume rm navinum-speckit_database_data
docker-compose up -d
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### Migrations en erreur

Solution : Vérifier le schéma :
```bash
docker-compose exec app php bin/console doctrine:schema:validate
docker-compose exec app php bin/console doctrine:schema:update --dump-sql
```

### Cache problématique

Solution : Nettoyer complètement :
```bash
docker-compose exec app rm -rf var/cache/*
docker-compose exec app php bin/console cache:clear
docker-compose exec app php bin/console cache:warmup
```

## Architecture Docker

### Services

- **app** : FrankenPHP + Symfony (port 80/443)
- **database** : PostgreSQL 16 Alpine
- **mercure** : Hub Mercure pour temps réel (port 3000)

### Volumes

- `database_data` : Données PostgreSQL persistantes
- `caddy_data` : Certificats SSL Caddy
- `caddy_config` : Configuration Caddy

### Réseau

- `navinum_network` : Réseau bridge pour communication inter-conteneurs

## Sécurité

- ✅ APP_DEBUG=0 en production
- ✅ Secrets configurables
- ✅ TRUSTED_PROXIES configuré
- ✅ CORS configuré
- ✅ Healthchecks activés
