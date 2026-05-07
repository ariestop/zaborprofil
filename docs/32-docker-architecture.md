# 32. Docker architecture

> **Docker — только для local development**. Staging и production не используют Docker. См. [ADR-0008](adr/0008-docker-for-local-development.md), [ADR-0009](adr/0009-vps-deployment-strategy.md).

## Состав compose

`docker-compose.yml`:

| Сервис | Образ | Назначение | Порт хоста |
|---|---|---|---|
| `app` | build `docker/php/Dockerfile` (`php:8.5-fpm-bookworm`) | PHP-FPM + Composer + Symfony | через nginx |
| `nginx` | `nginx:1.30.0-alpine` | Web server | `80` (`HTTP_PORT`) |
| `postgres` | `postgres:18` | БД | `15432` (`POSTGRES_PORT`) |
| `redis` | `redis:8-alpine` | Cache (Symfony Cache pools). Messenger использует Doctrine transport — см. [ADR-0007](adr/0007-redis-cache-and-messenger.md). | `16379` (`REDIS_PORT`) |
| `node` | build `docker/node/Dockerfile` (`node:25.9.0-bookworm`) | Vite/npm | dev server `5173` (`VITE_PORT`) |
| `mailpit` | `axllent/mailpit:latest` | SMTP test inbox | `8025` (`MAILPIT_PORT`) |
| `adminer` | `adminer:latest` | UI для Postgres | `8080` (`ADMINER_PORT`) |

## Volumes

| Volume | Куда монтируется | Что |
|---|---|---|
| `postgres_data` | `/var/lib/postgresql` | Данные БД |
| `redis_data` | `/data` | Append-only Redis |
| `uploads_data` | `/var/www/html/public_html/uploads` | User uploads (между app и nginx) |
| Bind mount `./` | `/var/www/html` (на app) и `/var/www/html` ro (на nginx) | Исходный код проекта |

Volumes являются локальным runtime-состоянием конкретного ПК разработки. Их не экспортируют в Git и не используют как способ синхронизации окружений. Единое dev-состояние БД воспроизводится из migrations + fixtures; при необходимости допускается только маленький обезличенный dev snapshot, описанный в [47-dev-database-state](47-dev-database-state.md).

## Networks

Compose использует default bridge network. Сервисы доступны по именам (`postgres`, `redis`, `mailpit`).

## Healthchecks

- `postgres`: `pg_isready -U $POSTGRES_USER -d $POSTGRES_DB`.
- `redis`: `redis-cli ping`.
- `app`/`nginx`: целевое — добавить healthcheck через `curl -f http://localhost/health` (см. [29-healthchecks](29-healthchecks.md)).

## Restart policies

`unless-stopped` для всех stateful сервисов. `node` — без restart (one-off для билдов).

## Non-root users

- `php` запускается под `root` для миграций cache при первом старте, далее работает под `www-data`. Это сделано через `command:` в `docker-compose.yml` (chown и потом `php-fpm`).
- На production в systemd php-fpm обязательно работает под `www-data`.
- Целевое: убрать `user: root` из dev compose, вынести bootstrap в init-script.

## Image build practices

`docker/php/Dockerfile`:

- Multi-stage build (целевое — сейчас single-stage).
- `composer install --no-dev` в production-target (если будем строить prod-image).
- Кеш слоёв через `composer install` отдельным слоем.
- Копирование `/var/www/html` через bind mount в dev — на production в release-папку.

## Difference dev vs prod

| Аспект | Dev (Docker) | Prod (VPS, без Docker) |
|---|---|---|
| Web server | `nginx:1.30.0-alpine` | nginx 1.30+ из репозиториев Debian/Ubuntu |
| PHP | `php:8.5-fpm-bookworm` | system php8.5-fpm |
| Postgres | `postgres:18` контейнер | system postgresql-18 |
| Redis | `redis:8-alpine` | system redis-server 8 |
| Node | контейнер | используется только во время deploy для build |
| Web root | bind-mount `./public_html` | `current/public_html` |
| Uploads | volume `uploads_data` | shared `shared/public_html/uploads` |
| Logs | `var/log/` (через bind) | shared `shared/var/log` |
| Cache | `var/cache/` (через bind) | release-local `var/cache/` |
| Secrets | `.env.local` (gitignored) | `shared/.env.local` |
| HTTPS | http only | TLS (Let’s Encrypt / certbot) |
| Profiler | `dev` env | прибит к 404 |

Версии PHP/Postgres/Redis должны совпадать между dev и prod (это правило).

## Risks Docker ↔ VPS divergence

- Разные версии PHP-extensions (например, `pgsql` build flags).
- Разные конфигурации `php.ini` (`memory_limit`, `max_execution_time`).
- Разная сетевая модель (Docker bridge vs unix-сокеты на VPS).
- Mitigation:
  - В CI запускать тесты на тех же версиях, что и в Docker;
  - Финальные smoke-тесты — на staging VPS, не только локально;
  - Версии runtime фиксируются в `docker/php/Dockerfile` и в `tools/deploy/templates/`.

## Что НЕЛЬЗЯ

- Использовать Docker для staging/production без отдельного решения и обновления документации.
- Хранить в Docker volumes секреты — секреты только в `.env.local`.
- Коммитить Docker images, Docker volumes, production/staging dumps или реальные backups БД в Git.
- Запускать `composer install --no-dev` локально без необходимости (теряем dev-зависимости).
- Опубликовывать Adminer/Mailpit на доступный извне IP.

## Сценарии

### Старт окружения

```bash
cp .env.local.example .env.local
make build
make up
make composer-install
make npm-install
make migrate
make npm-build
make health
```

### Ежедневная работа

```bash
make up
make shell
make logs
make test
```

### Полный сброс БД

```bash
make reset-db
```

`make reset-db` удаляет локальную БД, создаёт её заново, применяет migrations и запускает `make fixtures`. Если fixtures ещё не подключены, шаг остаётся безопасным no-op.

### Остановка

```bash
make down
```

## Связанные документы

- [33-local-development](33-local-development.md)
- [34-deployment](34-deployment.md)
- [33-local-development](33-local-development.md)
- [adr/0008-docker-for-local-development](adr/0008-docker-for-local-development.md)
