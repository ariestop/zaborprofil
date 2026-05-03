# AGENTS.md

Контекст для Cursor, Codex и других AI-агентов, работающих с проектом `zaborprofil`.

## Назначение проекта

Это новый production-ready Symfony CMS Engine для корпоративного сайта `zaborprofil.ru`.
Он заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

## Текущий стек

Минимальные зафиксированные версии runtime:

- PHP `>=8.5`
- Node.js `>=25.9.0`
- npm `>=11.12.1` (поставляется с Node.js 25.9.0)
- nginx `>=1.30.0`
- PostgreSQL `>=18`
- Redis `>=8`

Прочее:

- Symfony `8.x`
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig для публичного SSR
- Tailwind CSS (`@tailwindcss/typography`), Vite
- Vue 3 для будущей админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker используется только для local development. Staging и production должны оставаться native VPS stack: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based release deploy.

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, value objects, infrastructure adapters и UI entrypoints.
- `src/Module/` — модули модульного монолита.
- `templates/` — Twig-шаблоны публичного сайта и админки.
- `assets/site/` — frontend публичного сайта.
- `assets/admin/` — Vue 3 entrypoint будущей админки.
- `docs/` — документация на русском языке.

## Архитектурные правила

- Соблюдать Clean Architecture и Modular Monolith.
- Контроллеры должны быть тонкими: request -> DTO/валидация -> use case -> response.
- Не размещать бизнес-логику в контроллерах.
- Не превращать Doctrine Entity в god object.
- Не добавлять WordPress importer, shortcode parser, EAV без необходимости или EasyAdmin как основную админку.
- Не делать Docker обязательной зависимостью для staging или production.
- Публичный сайт должен оставаться SSR на Symfony + Twig, не SPA.
- Все PHP-файлы должны использовать `declare(strict_types=1)`.
- Документация пишется на русском языке.

## Проверки

Перед завершением backend/frontend изменений по возможности запускать:

```bash
composer validate --strict
composer check:syntax
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
vendor/bin/phpunit
npm run build
```

Локально для Doctrine/PostgreSQL требуется включенное расширение PHP `pdo_pgsql`.

## Cursor Cloud specific instructions

### Starting the development environment

All services run via Docker Compose. After the update script finishes, start services:

```bash
dockerd &
sleep 3
cd /workspace
docker compose up -d --remove-orphans
```

Then install deps and run migrations inside containers:

```bash
docker compose exec -T --user root app sh -c 'git config --global --add safe.directory /var/www/html && mkdir -p vendor var/cache var/log var/share && chown -R www-data:www-data vendor var'
docker compose exec -T --user www-data app composer install
docker compose exec -T --user root node sh -c 'mkdir -p /var/www/html/node_modules && chown -R node:node /var/www/html/node_modules'
docker compose exec -T node npm ci
docker compose exec -T --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T --user root node sh -c 'mkdir -p /var/www/html/public_html/build && chown -R node:node /var/www/html/public_html/build'
docker compose exec -T node npm run build
```

### Running checks inside Docker

All PHP tools (phpstan, cs-fixer, rector, phpunit) must run inside the `app` container because they need PHP 8.5. Use `--user root` with `COMPOSER_ALLOW_SUPERUSER=1` or `--user www-data`:

```bash
docker compose exec -T --user root app sh -c 'COMPOSER_ALLOW_SUPERUSER=1 composer validate --strict'
docker compose exec -T --user root app sh -c 'COMPOSER_ALLOW_SUPERUSER=1 composer check:syntax'
docker compose exec -T --user root app sh -c 'COMPOSER_ALLOW_SUPERUSER=1 php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi'
docker compose exec -T --user root app sh -c 'COMPOSER_ALLOW_SUPERUSER=1 php vendor/bin/phpstan analyse'
docker compose exec -T --user root app sh -c 'COMPOSER_ALLOW_SUPERUSER=1 php vendor/bin/rector process --dry-run --ansi'
```

Tests use SQLite (no PostgreSQL needed):

```bash
docker compose exec -T --user root app sh -c 'APP_ENV=test APP_SECRET=test-secret DATABASE_URL="sqlite:///%kernel.cache_dir%/test.db" REDIS_URL=redis://redis:6379/1 MESSENGER_TRANSPORT_DSN=in-memory:// MAILER_DSN=null://null SITE_URL=https://zaborprofil.test DEFAULT_URI=https://zaborprofil.test php vendor/bin/phpunit'
```

Frontend build:

```bash
docker compose exec -T node npm run build
```

### Known issues

- The `docker/php/Dockerfile` fails to build in Cloud Agent VMs because `opcache` is already pre-installed in the `php:8.5-fpm-bookworm` image. The workaround is `docker/php/Dockerfile.cloud` (removes `opcache` from `docker-php-ext-install`) used via `docker-compose.override.yml`.
- The `composer.json` Symfony version constraints must stay at `^8.0` (not `^8.1`) since Symfony 8.1 is not yet released as stable.
- Admin login CSRF uses Symfony's `SameOriginCsrfTokenManager` — curl-based API calls require the `Origin: http://localhost` header.
- Permission fixes are needed after `docker compose up`: `vendor/`, `var/`, `node_modules/`, and `public_html/build/` directories need correct ownership for their respective container users.

### Verifying the app is running

```bash
curl -fsS http://localhost/health
```

All checks should return `"status": "ok"`.
