# Локальная разработка через Docker

Docker нужен только для локальной разработки на macOS, Windows через Docker Desktop + WSL2 и Linux. Staging и production разворачиваются без Docker.

## Требования

- Docker Desktop или Docker Engine с Compose v2.
- На Windows проект лучше хранить внутри WSL2, например `~/projects/zaborprofil`, а не на диске `C:`.
- Свободные порты на хосте по умолчанию: `80` (Nginx), `15432` (PostgreSQL), `16379` (Redis), `8025` (Mailpit), `8080` (Adminer), `5173` (Vite dev).

PostgreSQL и Redis намеренно публикуются на нестандартных портах хоста (`15432`/`16379`), чтобы не конфликтовать с локально установленными `postgres`/`redis`. Внутри Docker-сети сервисы доступны по штатным `5432`/`6379`. Хост-порты можно переопределить в `.env.local`:

```dotenv
POSTGRES_PORT=15432
REDIS_PORT=16379
HTTP_PORT=80
```

## Правило работы с БД

Для локальной разработки и запуска тестов используется PostgreSQL только из Docker-контейнера `postgres`. Не запускайте локальные миграции, fixtures, `make test`, `make quality` или Symfony-команды против установленного на хосте PostgreSQL: это приводит к расхождению версий, ролей и схемы.

Приложение внутри Docker-сети подключается к БД через `postgres:5432`. Хост подключается к той же контейнерной БД через опубликованный порт `15432`. Test DB создаётся командой `make test-db` и используется `make test` как `zaborprofil_test` внутри контейнера.

Подключиться к Postgres с хоста:

```bash
psql "postgresql://zaborprofil:zaborprofil@127.0.0.1:15432/zaborprofil"
```

Если порт `80` занят, укажите другой порт в `.env.local`:

```dotenv
HTTP_PORT=8081
SITE_URL="http://localhost:8081"
DEFAULT_URI="http://localhost:8081"
```

## Первый запуск

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

`make init` и `make reset-db` приводят локальную БД к единому dev-состоянию через Doctrine migrations и `make fixtures`. Если `doctrine/doctrine-fixtures-bundle` ещё не подключён, fixture-шаг будет no-op с информационным сообщением.

После запуска доступны:

- `http://localhost`
- `http://localhost/admin`
- `http://localhost/health`
- `http://localhost:8025` — Mailpit
- `http://localhost:8080` — Adminer

## Контейнеры

- `app` — PHP-FPM 8.5 (`php:8.5-fpm-bookworm`, плавающий patch внутри 8.5.x), Composer, Symfony CLI, PHP extensions.
- `nginx` — `nginx:1.30.0-alpine`, web server с root `public_html/`.
- `postgres` — PostgreSQL 18.
- `redis` — Redis 8.
- `node` — `node:25.9.0-bookworm` (включает npm 11.12.1) для Vite/npm.
- `mailpit` — тестирование писем.
- `adminer` — управление PostgreSQL.

## Ежедневные команды

```bash
make init
make up
make shell
make logs
make cache-clear
make migrate
make test
make quality
make smoke
make down
```

Composer:

```bash
make composer-install
```

NPM:

```bash
make npm-install
make npm-dev
make npm-build
```

База данных:

```bash
make db
make reset-db
```

Redis:

```bash
make redis
```

## Xdebug

По умолчанию Xdebug выключен:

```dotenv
XDEBUG_MODE=off
```

Для включения:

```dotenv
XDEBUG_MODE=develop,debug
PHP_IDE_CONFIG=serverName=zaborprofil-docker
XDEBUG_CONFIG="client_host=host.docker.internal client_port=9003"
```

Затем перезапустите PHP:

```bash
make restart
```

## Volumes

Docker использует именованные volumes:

- `postgres_data` — данные PostgreSQL.
- `redis_data` — данные Redis.
- `uploads_data` — `public_html/uploads`.

Секреты не должны храниться в volumes. Реальные значения храните только в `.env.local`, который игнорируется Git.

Volumes локальны для конкретного ПК и не являются переносимым состоянием проекта. В Git фиксируются только схема и воспроизводимые dev-данные: migrations, fixtures и при необходимости маленький обезличенный dev snapshot. Реальные backups PostgreSQL, production/staging dumps, персональные данные и uploads из локального volume в Git не добавляются.

## Dev snapshot БД

Основной способ синхронизировать БД между ПК разработки:

```bash
make reset-db
```

Если fixtures недостаточно и нужен общий демонстрационный набор данных, допускается добавить в Git только проверенный dev snapshot без секретов и персональных данных. Это не backup production и не замена normal backup/restore. Правила описаны в `docs/47-dev-database-state.md`.

## Проверка окружения

```bash
docker compose ps
make health
curl -fsS http://localhost/health
```
