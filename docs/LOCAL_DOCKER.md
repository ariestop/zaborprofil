# Локальная разработка через Docker

Docker нужен только для локальной разработки на macOS, Windows через Docker Desktop + WSL2 и Linux. Staging и production разворачиваются без Docker.

## Требования

- Docker Desktop или Docker Engine с Compose v2.
- На Windows проект лучше хранить внутри WSL2, например `~/projects/zaborprofil`, а не на диске `C:`.
- Свободные порты: `80`, `5432`, `6379`, `8025`, `8080`, `5173`.

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

После запуска доступны:

- `http://localhost`
- `http://localhost/admin`
- `http://localhost/health`
- `http://localhost:8025` — Mailpit
- `http://localhost:8080` — Adminer

## Контейнеры

- `app` — PHP-FPM 8.4, Composer, Symfony CLI, PHP extensions.
- `nginx` — web server с root `public_html/`.
- `postgres` — PostgreSQL 18.
- `redis` — Redis 8.
- `node` — Node.js LTS для Vite/npm.
- `mailpit` — тестирование писем.
- `adminer` — управление PostgreSQL.

## Ежедневные команды

```bash
make up
make shell
make logs
make cache-clear
make migrate
make test
make quality
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

## Проверка окружения

```bash
docker compose ps
make health
curl -fsS http://localhost/health
```
