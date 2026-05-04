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

DOCKER_UID ?= 1000
DOCKER_GID ?= 1000

PHP = $(COMPOSE) exec -T --user www-data app
PHP_SHELL = $(COMPOSE) exec --user www-data app
NODE = $(COMPOSE) exec -T --user $(DOCKER_UID):$(DOCKER_GID) node
POSTGRES = $(COMPOSE) exec -T postgres
REDIS = $(COMPOSE) exec -T redis
POSTGRES_DB ?= zaborprofil
POSTGRES_USER ?= zaborprofil
POSTGRES_PASSWORD ?= zaborprofil
TEST_DATABASE_NAME ?= $(POSTGRES_DB)_test
TEST_DATABASE_URL ?= postgresql://$(POSTGRES_USER):$(POSTGRES_PASSWORD)@postgres:5432/$(TEST_DATABASE_NAME)?serverVersion=18&charset=utf8
TEST_ENV = env APP_ENV=test APP_SECRET=test-secret DATABASE_URL='$(TEST_DATABASE_URL)' REDIS_URL=redis://redis:6379/1 MESSENGER_TRANSPORT_DSN=in-memory:// MAILER_DSN=null://null SITE_URL=https://zaborprofil.test DEFAULT_URI=https://zaborprofil.test

.PHONY: init up down restart build shell composer-install npm-install npm-dev npm-build migrate migration fixtures test-db test phpstan cs cs-fix rector quality smoke cache-clear logs db redis reset-db health

init: build up composer-install npm-install migrate npm-build smoke

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
	$(COMPOSE) exec --user $(DOCKER_UID):$(DOCKER_GID) node npm run dev -- --host 0.0.0.0

npm-build:
	$(NODE) npm run build

migrate:
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

migration:
	$(PHP) php bin/console doctrine:migrations:diff

fixtures:
	$(PHP) sh -lc 'php bin/console list doctrine:fixtures >/dev/null 2>&1 && php bin/console doctrine:fixtures:load --no-interaction || echo "Doctrine fixtures are not installed."'

test-db:
	$(POSTGRES) sh -lc 'createdb -U "$${POSTGRES_USER:-zaborprofil}" "$(TEST_DATABASE_NAME)" 2>/dev/null || true'

test: test-db
	$(PHP) $(TEST_ENV) php vendor/bin/phpunit

phpstan:
	$(PHP) php vendor/bin/phpstan analyse

cs:
	$(PHP) php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi

cs-fix:
	$(PHP) php vendor/bin/php-cs-fixer fix --ansi

rector:
	$(PHP) php vendor/bin/rector process --dry-run --ansi

quality: test-db
	$(PHP) composer validate --strict
	$(PHP) composer check:syntax
	$(PHP) php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi
	$(PHP) php vendor/bin/phpstan analyse
	$(PHP) php vendor/bin/rector process --dry-run --ansi
	$(PHP) $(TEST_ENV) php bin/console doctrine:migrations:status --env=test --no-interaction
	$(PHP) $(TEST_ENV) php bin/console doctrine:schema:validate --env=test --no-interaction --skip-sync
	$(PHP) $(TEST_ENV) php bin/console lint:container --env=test --no-interaction
	$(PHP) $(TEST_ENV) php bin/console lint:twig templates --env=test --no-interaction
	$(PHP) $(TEST_ENV) php vendor/bin/phpunit
	$(PHP) $(TEST_ENV) php bin/console app:smoke:test
	$(NODE) npm run build

smoke: test-db
	$(PHP) $(TEST_ENV) php bin/console app:smoke:test

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
	$(PHP) sh -lc 'php bin/console list doctrine:fixtures >/dev/null 2>&1 && php bin/console doctrine:fixtures:load --no-interaction || echo "Doctrine fixtures are not installed."'

health:
	curl -fsS "$${SITE_URL:-http://localhost}/health"
