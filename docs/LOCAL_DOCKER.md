# Локальная разработка через Docker

Docker нужен только для локальной разработки на macOS, Windows через Docker Desktop + WSL2 и Linux. Staging и production разворачиваются без Docker.

## Требования

- Docker Desktop или Docker Engine с Compose v2.
- На Windows проект лучше хранить внутри WSL2, например `~/projects/zaborprofil`, а не на диске `C:`.
- Свободные порты на хосте по умолчанию: `80` (Nginx), `15432` (PostgreSQL), `16379` (Redis), `8025` (Mailpit), `8080` (Adminer), `5173` (Vite dev).

## Windows + WSL2

На Windows поддерживаемый local development flow:

1. Установить Docker Desktop.
2. Включить WSL2 backend: Docker Desktop → Settings → General → `Use the WSL 2 based engine`.
3. Включить интеграцию с Ubuntu: Settings → Resources → WSL Integration.
4. Клонировать или держать проект внутри WSL, например `~/zaborprofil`.
5. Запускать команды из Ubuntu/WSL terminal.

Путь `\\wsl.localhost\Ubuntu\home\<user>\zaborprofil` подходит для открытия проекта в Cursor на Windows. Для команд используйте Linux-путь:

```bash
cd ~/zaborprofil
```

Если в WSL нет `make`:

```bash
sudo apt update
sudo apt install -y make
```

Не запускайте `make init`, `make test`, `make quality`, `composer install` или `npm install` из PowerShell/CMD против UNC-пути. Это создаёт проблемы с правами, скоростью файловой системы и путями внутри Docker.

PostgreSQL и Redis намеренно публикуются на нестандартных портах хоста (`15432`/`16379`), чтобы не конфликтовать с локально установленными `postgres`/`redis`. Внутри Docker-сети сервисы доступны по штатным `5432`/`6379`. Хост-порты можно переопределить в `.env.local`:

```dotenv
POSTGRES_PORT=15432
REDIS_PORT=16379
HTTP_PORT=80
```

В `HTTP_PORT` допускается привязка только к loopback, например `127.0.0.1:8081` (см. [27-config-and-env](27-config-and-env.md)). Тогда с другой машины в LAN/VPN сайт по IP сервера не откроется — это ожидаемо.

Если нужен доступ с другого устройства (например, через Tailscale) без SSH-туннеля, используйте:

```dotenv
HTTP_PORT=8081
SITE_URL=http://<server-ip>:8081
DEFAULT_URI=http://<server-ip>:8081
```

Не открывайте наружу `POSTGRES_PORT`, `REDIS_PORT`, `MAILPIT_PORT`, `ADMINER_PORT` без отдельной причины.

### Удалённая разработка (браузер не там, где Docker)

Если репозиторий и `docker compose` на сервере, а браузер на ноутбуке, `http://127.0.0.1:…` на ноутбуке указывает на сам ноутбук, а не на сервер. Нужен **SSH local port forwarding** (`ssh -L …`), постоянный `LocalForward` в `~/.ssh/config`, `autossh` или forwarding вкладки **Ports** в Cursor/VS Code при Remote SSH. Подробные команды и примеры — в [33-local-development](33-local-development.md) (раздел «Удалённый сервер»).

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

Если запускаете Compose напрямую, а не через `make`, всегда передавайте env-file:

```bash
docker compose --env-file .env.local up -d
```

Для обычного первого запуска можно использовать агрегированную команду:

```bash
cp .env.local.example .env.local
make init
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
