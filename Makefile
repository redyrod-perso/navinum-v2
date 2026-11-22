# Makefile pour Navinum SpecKit
# Gestion du serveur FrankenPHP et tâches courantes

.PHONY: help start stop restart status logs clear-cache install test

# Variables
PORT := 8002
PHP_VERSION := 8.3
CONSOLE := bin/console

# Couleurs pour l'affichage
YELLOW := \033[33m
GREEN := \033[32m
RED := \033[31m
BLUE := \033[34m
NC := \033[0m # No Color

# Commande par défaut
help: ## Affiche cette aide
	@echo "$(BLUE)Navinum SpecKit - Commandes disponibles:$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "$(YELLOW)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ""

##
## 🚀 Serveur FrankenPHP
##

start: ## Démarre le serveur FrankenPHP sur le port 8002
	@echo "$(GREEN)🚀 Démarrage de FrankenPHP sur le port $(PORT)...$(NC)"
	@if pgrep -f "frankenphp.*:$(PORT)" > /dev/null; then \
		echo "$(YELLOW)⚠️  Le serveur est déjà en cours d'exécution$(NC)"; \
		echo ""; \
		echo "$(GREEN)🌐 URLs disponibles:$(NC)"; \
		echo "$(BLUE)   📍 Application: http://localhost:$(PORT)$(NC)"; \
		echo "$(BLUE)   📍 Admin Parcours: http://localhost:$(PORT)/admin/parcours$(NC)"; \
		echo ""; \
	else \
		nohup frankenphp php-server --listen :$(PORT) --root ./public > var/log/frankenphp.log 2>&1 & \
		echo $$! > var/frankenphp.pid; \
		sleep 2; \
		if pgrep -f "frankenphp.*:$(PORT)" > /dev/null; then \
			echo "$(GREEN)✅ Serveur démarré avec succès$(NC)"; \
			echo ""; \
			echo "$(GREEN)🌐 URLs disponibles:$(NC)"; \
			echo "$(BLUE)   📍 Application: http://localhost:$(PORT)$(NC)"; \
			echo "$(BLUE)   📍 Admin Parcours: http://localhost:$(PORT)/admin/parcours$(NC)"; \
			echo "$(BLUE)   📍 API Platform: http://localhost:$(PORT)/api$(NC)"; \
			echo ""; \
			echo "$(YELLOW)💡 Utilisez 'make stop' pour arrêter le serveur$(NC)"; \
		else \
			echo "$(RED)❌ Erreur lors du démarrage$(NC)"; \
			cat var/log/frankenphp.log; \
		fi; \
	fi

stop: ## Arrête le serveur FrankenPHP
	@echo "$(YELLOW)🛑 Arrêt du serveur FrankenPHP...$(NC)"
	@if [ -f var/frankenphp.pid ]; then \
		PID=$$(cat var/frankenphp.pid); \
		if kill $$PID 2>/dev/null; then \
			echo "$(GREEN)✅ Serveur arrêté (PID: $$PID)$(NC)"; \
		else \
			echo "$(YELLOW)⚠️  Process introuvable, tentative de recherche...$(NC)"; \
		fi; \
		rm -f var/frankenphp.pid; \
	fi
	@pkill -f "frankenphp.*:$(PORT)" 2>/dev/null || true
	@sleep 1
	@if ! pgrep -f "frankenphp.*:$(PORT)" > /dev/null; then \
		echo "$(GREEN)✅ Serveur complètement arrêté$(NC)"; \
	else \
		echo "$(RED)❌ Le serveur semble toujours actif$(NC)"; \
	fi

force-stop: ## Force l'arrêt du serveur FrankenPHP
	@echo "$(RED)🔥 Arrêt forcé de tous les processus FrankenPHP...$(NC)"
	@pkill -9 -f "frankenphp" 2>/dev/null || true
	@rm -f var/frankenphp.pid
	@echo "$(GREEN)✅ Arrêt forcé terminé$(NC)"

restart: ## Redémarre le serveur FrankenPHP
	@echo "$(BLUE)🔄 Redémarrage du serveur...$(NC)"
	@$(MAKE) stop
	@sleep 1
	@$(MAKE) start

status: ## Affiche le statut du serveur
	@echo "$(BLUE)📊 Statut du serveur FrankenPHP:$(NC)"
	@if pgrep -f "frankenphp.*:$(PORT)" > /dev/null; then \
		PID=$$(pgrep -f "frankenphp.*:$(PORT)"); \
		echo "$(GREEN)✅ Serveur actif (PID: $$PID)$(NC)"; \
		echo "$(BLUE)📍 URL: http://localhost:$(PORT)$(NC)"; \
		echo "$(BLUE)⏰ Depuis: $$(ps -o lstart= -p $$PID)$(NC)"; \
	else \
		echo "$(RED)❌ Serveur arrêté$(NC)"; \
	fi

logs: ## Affiche les logs du serveur
	@echo "$(BLUE)📄 Logs FrankenPHP:$(NC)"
	@if [ -f var/log/frankenphp.log ]; then \
		tail -f var/log/frankenphp.log; \
	else \
		echo "$(YELLOW)⚠️  Fichier de log introuvable$(NC)"; \
	fi

##
## 🛠️ Développement
##

install: ## Installe les dépendances du projet
	@echo "$(GREEN)📦 Installation des dépendances...$(NC)"
	@composer install
	@if [ -f package.json ]; then npm install; fi
	@$(MAKE) setup-dirs

setup-dirs: ## Créé les répertoires nécessaires
	@echo "$(BLUE)📁 Création des répertoires...$(NC)"
	@mkdir -p var/log var/sessions var/cache
	@chmod 755 var/log var/sessions var/cache

clear-cache: ## Vide le cache Symfony
	@echo "$(YELLOW)🧹 Nettoyage du cache...$(NC)"
	@$(CONSOLE) cache:clear
	@echo "$(GREEN)✅ Cache vidé$(NC)"

assets: ## Compile les assets (si Webpack Encore)
	@if [ -f webpack.config.js ]; then \
		echo "$(BLUE)🎨 Compilation des assets...$(NC)"; \
		npm run build; \
	else \
		echo "$(YELLOW)⚠️  Webpack Encore non configuré$(NC)"; \
	fi

##
## 🧪 Tests et validation
##

test: ## Lance les tests PHPUnit
	@echo "$(BLUE)🧪 Exécution des tests...$(NC)"
	@if [ -d tests ]; then \
		php bin/phpunit; \
	else \
		echo "$(YELLOW)⚠️  Répertoire tests introuvable$(NC)"; \
	fi

validate: ## Valide la configuration Symfony
	@echo "$(BLUE)✅ Validation de la configuration...$(NC)"
	@$(CONSOLE) lint:container
	@$(CONSOLE) debug:config sylius_resource
	@$(CONSOLE) debug:router | grep parcours || echo "Routes parcours non trouvées"

check-requirements: ## Vérifie les prérequis système
	@echo "$(BLUE)🔍 Vérification des prérequis...$(NC)"
	@php --version | head -1
	@composer --version 2>/dev/null || echo "❌ Composer non installé"
	@frankenphp version 2>/dev/null || echo "❌ FrankenPHP non installé"
	@echo "$(GREEN)✅ Vérification terminée$(NC)"

##
## 🗃️ Base de données
##

db-create: ## Créé la base de données
	@echo "$(BLUE)🗃️  Création de la base de données...$(NC)"
	@$(CONSOLE) doctrine:database:create --if-not-exists
	@echo "$(GREEN)✅ Base de données créée$(NC)"

db-migrate: ## Applique les migrations
	@echo "$(BLUE)📊 Application des migrations...$(NC)"
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction
	@echo "$(GREEN)✅ Migrations appliquées$(NC)"

db-fixtures: ## Charge les fixtures (données de test)
	@echo "$(BLUE)🌱 Chargement des fixtures...$(NC)"
	@$(CONSOLE) doctrine:fixtures:load --no-interaction
	@echo "$(GREEN)✅ Fixtures chargées$(NC)"

db-reset: ## Remet à zéro la base de données
	@echo "$(YELLOW)🔄 Remise à zéro de la base...$(NC)"
	@$(CONSOLE) doctrine:database:drop --force --if-exists
	@$(MAKE) db-create
	@$(MAKE) db-migrate
	@echo "$(GREEN)✅ Base de données remise à zéro$(NC)"

##
## 🧹 Nettoyage
##

clean: ## Nettoie les fichiers temporaires
	@echo "$(YELLOW)🧹 Nettoyage des fichiers temporaires...$(NC)"
	@rm -rf var/cache/* var/log/* var/sessions/*
	@rm -f var/frankenphp.pid
	@echo "$(GREEN)✅ Nettoyage terminé$(NC)"

sessions-reset: ## Réinitialise toutes les sessions
	@echo "$(YELLOW)🔄 Réinitialisation des sessions...$(NC)"
	@rm -rf var/cache/sessions/*.json 2>/dev/null || true
	@echo "$(GREEN)✅ Sessions réinitialisées$(NC)"


##
## 📊 Informations
##

info: ## Affiche les informations du projet
	@echo "$(BLUE)📋 Informations du projet Navinum SpecKit:$(NC)"
	@echo "Port serveur: $(PORT)"
	@echo "Version PHP: $(PHP_VERSION)"
	@echo "Répertoire: $(PWD)"
	@$(MAKE) status

##
## 🚀 Commandes rapides
##

dev: start ## Alias pour 'make start'

serve: start ## Alias pour 'make start'

build: clear-cache assets ## Construit le projet complet
	@echo "$(GREEN)🏗️  Projet construit avec succès$(NC)"