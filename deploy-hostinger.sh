#!/bin/bash

# =========================================
# Script de déploiement Hostinger
# Usage: ./deploy-hostinger.sh
# =========================================

set -e  # Arrêt en cas d'erreur

echo "🚀 Déploiement sur Hostinger - activites-touristiques.online"
echo ""

# 1. Vérifier qu'on est sur Hostinger
if [[ ! $(hostname) =~ "srv802003" ]]; then
    echo "⚠️  Ce script doit être exécuté sur le serveur Hostinger (srv802003)"
    echo "Connectez-vous avec: ssh root@srv802003.hstgr.cloud"
    exit 1
fi

# 2. Aller dans le bon répertoire
cd ~/navinum-v2 || { echo "❌ Dossier ~/navinum-v2 introuvable"; exit 1; }

# 3. Pull des derniers changements
echo "📥 Pull des derniers changements..."
git pull origin main

# 4. Copier .env.prod vers .env.local
echo "🔧 Configuration production..."
cp .env.prod .env.local

# 5. Build et démarrage avec docker-compose.prod.yml
echo "🐳 Build et démarrage des containers..."
docker compose -f docker-compose.yaml -f docker-compose.prod.yml down
docker compose -f docker-compose.yaml -f docker-compose.prod.yml build --no-cache
docker compose -f docker-compose.yaml -f docker-compose.prod.yml up -d

# 6. Attendre que les services démarrent
echo "⏳ Attente du démarrage..."
sleep 10

# 7. Migrations
echo "💾 Migrations de la base de données..."
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# 8. Clear cache
echo "🧹 Clear cache production..."
docker compose exec -T app php bin/console cache:clear --env=prod

# 9. Vérifier le statut
echo ""
echo "📊 Statut des containers:"
docker compose ps

echo ""
echo "✅ Déploiement terminé !"
echo ""
echo "🌐 Application:"
echo "   http://activites-touristiques.online (redirigé par Traefik)"
echo "   https://activites-touristiques.online (si SSL configuré)"
echo ""
echo "📋 Commandes utiles:"
echo "   - Logs: docker compose logs -f app"
echo "   - Restart: docker compose restart app"
echo "   - Status: docker compose ps"
echo ""
