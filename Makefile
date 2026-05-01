COMPOSE ?= docker compose
COMPOSE_ENV_FILE := $(if $(wildcard .env.local),--env-file .env.local,)
COMPOSE := $(COMPOSE) $(COMPOSE_ENV_FILE)

# Expose .env.local values (SITE_URL, HTTP_PORT, etc.) to make targets like
# `make health` so the developer experience matches the value the user put
# in their .env.local without requiring an extra `source` step.
ifneq (,$(wildcard .env.local))
include .env.local
export
endif
PHP = $(COMPOSE) exec -T --user www-data app
PHP_SHELL = $(COMPOSE) exec --user www-data app
NODE = $(COMPOSE) exec -T node
POSTGRES = $(COMPOSE) exec -T postgres
REDIS = $(COMPOSE) exec -T redis
TEST_ENV = env APP_ENV=test APP_SECRET=test-secret DATABASE_URL='sqlite:///%kernel.cache_dir%/test.db' REDIS_URL=redis://redis:6379/1 MESSENGER_TRANSPORT_DSN=in-memory:// MAILER_DSN=null://null SITE_URL=https://zaborprofil.test DEFAULT_URI=https://zaborprofil.test

.PHONY: up down restart build shell composer-install npm-install npm-dev npm-build migrate migration fixtures test phpstan cs cs-fix rector quality cache-clear logs db redis reset-db health

up:
	$(COMPOSE) up -d --remove-orphans

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

build:
	$(COMPOSE) build --pull

shell:
	$(PHP_SHELL) sh

composer-install:
	$(PHP) composer install

npm-install:
	$(NODE) npm ci

npm-dev:
	$(COMPOSE) exec node npm run dev -- --host 0.0.0.0

npm-build:
	$(NODE) npm run build

migrate:
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

migration:
	$(PHP) php bin/console doctrine:migrations:diff

fixtures:
	$(PHP) sh -lc 'php bin/console list doctrine:fixtures >/dev/null 2>&1 && php bin/console doctrine:fixtures:load --no-interaction || echo "Doctrine fixtures are not installed."'

test:
	$(PHP) $(TEST_ENV) php vendor/bin/phpunit

phpstan:
	$(PHP) php vendor/bin/phpstan analyse

cs:
	$(PHP) php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi

cs-fix:
	$(PHP) php vendor/bin/php-cs-fixer fix --ansi

rector:
	$(PHP) php vendor/bin/rector process --dry-run --ansi

quality:
	$(PHP) composer validate --strict
	$(PHP) php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi
	$(PHP) php vendor/bin/phpstan analyse
	$(PHP) php vendor/bin/rector process --dry-run --ansi
	$(PHP) $(TEST_ENV) php vendor/bin/phpunit
	$(NODE) npm run build

cache-clear:
	$(PHP) php bin/console cache:clear

logs:
	$(COMPOSE) logs -f --tail=200

db:
	$(POSTGRES) psql -U $${POSTGRES_USER:-zaborprofil} -d $${POSTGRES_DB:-zaborprofil}

redis:
	$(REDIS) redis-cli

reset-db:
	$(PHP) php bin/console doctrine:database:drop --force --if-exists
	$(PHP) php bin/console doctrine:database:create --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

health:
	curl -fsS "$${SITE_URL:-http://localhost}/health"
