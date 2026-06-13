.PHONY: help dev prod stop build migrate jwt-keys logs shell test

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

# Développement 

dev: ## Démarre en mode développement (avec compose.override.yaml)
	docker compose up -d

stop: ## Arrête tous les conteneurs
	docker compose down

build: ## Reconstruit les images sans cache
	docker compose build --no-cache

logs: ## Suit les logs en temps réel
	docker compose logs -f

shell: ## Ouvre un shell dans le conteneur app
	docker compose exec app sh

# Production 

prod: ## Démarre en mode production (compose.yaml uniquement)
	docker compose -f compose.yaml up -d --build

prod-stop: ## Arrête la prod
	docker compose -f compose.yaml down

# Base de données 

migrate: ## Exécute les migrations Doctrine
	docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

migrate-prod: ## Exécute les migrations en production
	docker compose -f compose.yaml exec app php bin/console doctrine:migrations:migrate --no-interaction

# JWT 

jwt-keys: ## Génère les clés RSA JWT (nécessite JWT_PASSPHRASE dans .env.local)
	@test -n "$(JWT_PASSPHRASE)" || (echo "Erreur : JWT_PASSPHRASE n'est pas défini. Exportez-le d'abord." && exit 1)
	@mkdir -p config/jwt
	openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:$(JWT_PASSPHRASE)
	openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:$(JWT_PASSPHRASE)
	@echo "Clés générées dans config/jwt/"

# Tests 

test: ## Lance toute la suite de tests
	docker compose exec -e APP_ENV=test app php bin/phpunit --testdox

test-setup: ## Crée et migre la base de données de test
	docker compose exec -e APP_ENV=test app php bin/console doctrine:database:create --if-not-exists
	docker compose exec -e APP_ENV=test app php bin/console doctrine:migrations:migrate --no-interaction
