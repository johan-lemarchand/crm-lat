make version.PHONY: version

version: ## Mise à jour interactive de version
	powershell -ExecutionPolicy Bypass -File version.ps1

.PHONY: deploy

deploy: ## Pull les changements et applique les migrations si nécessaires (PRODUCTION)
	export APP_ENV=prod && \
	git pull && \
	composer install --no-dev --optimize-autoloader && \
	php bin/console doctrine:migrations:status | findstr "New" && php bin/console doctrine:migrations:migrate --no-interaction || echo "No new migrations" && \
	php bin/console cache:clear && \
	php bin/console cache:warmup

.PHONY: cache

cache: ## Clear and warmup cache
	php bin/console cache:clear && \
	php bin/console cache:warmup


