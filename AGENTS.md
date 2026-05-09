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
- React + TypeScript для админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker используется только для local development. Staging и production должны оставаться native VPS stack: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based release deploy.

## Windows / WSL2 для агентов

- На Windows проект должен запускаться из Ubuntu/WSL terminal, даже если открыт в Cursor по пути `\\wsl.localhost\Ubuntu\home\...\zaborprofil`.
- Рабочая копия должна лежать внутри Linux-файловой системы WSL (`~/zaborprofil`, `/home/<user>/zaborprofil`), а не на `C:\`.
- Docker Desktop должен работать с WSL2 backend и включённой интеграцией с Ubuntu-дистрибутивом проекта.
- Все `make`, `docker compose`, `composer`, `npm`, PHPUnit и Doctrine-команды выполнять из WSL. Не запускать их из PowerShell/CMD против UNC-пути.
- Если порт `80` занят на Windows, использовать `.env.local`: `HTTP_PORT=8081`, `SITE_URL=http://localhost:8081`, `DEFAULT_URI=http://localhost:8081`.
- Канонический первый запуск на Windows:

```bash
cp .env.local.example .env.local
make init
make health
```

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, value objects, infrastructure adapters и UI entrypoints.
- `src/Module/` — модули модульного монолита.
- `templates/` — Twig-шаблоны публичного сайта и админки.
- `assets/site/` — frontend публичного сайта.
- `admin/` — React + TypeScript entrypoint админ-панели.
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
- Единое dev-состояние БД должно воспроизводиться из Doctrine migrations + fixtures/seed data. Не коммитить Docker images, Docker volumes, реальные PostgreSQL backups, production/staging dumps, uploads или секреты; в Git допустим только маленький обезличенный dev snapshot после ручной проверки.

## Проверки

### Запуск команд на сервере

- Для запуска shell-команд на сервере использовать `sudo -n` (non-interactive режим, без запроса пароля).
- Пример:

```bash
sudo -n npm install
```

- Это правило распространяется на команды `npm`, `composer`, `make`, `docker compose`, PHPUnit и другие проверки/сборки, выполняемые агентом на сервере.

### Локальный npm в Docker Compose

- Для локальных npm-команд использовать только цели `make`:
  - `make npm-install` (вместо прямого `npm install`/`npm ci`)
  - `make npm-build` (вместо прямого `npm run build`)
- Эти цели запускают `node`-сервис с `--user $(DOCKER_UID):$(DOCKER_GID)` и предотвращают поломку прав в bind mount.
- Не запускать npm-команды в `app`-контейнере под `root` против рабочей директории проекта: это создаёт root-owned файлы в `public_html/build/` и приводит к `EACCES` при `vite build` (например, на `public_html/build/.vite`).
- Если права уже сломаны, исправить владельца и повторить сборку через `make npm-build`:

```bash
docker compose exec -T app sh -lc 'chown -R 1000:1000 /var/www/html/public_html/build'
make npm-build
```

AI-агентам запрещено запускать PHPUnit/Doctrine проверки на SQLite. Локальные
тесты всегда выполняются внутри Docker Compose против PostgreSQL service
`postgres` и отдельной БД `zaborprofil_test`:

```bash
make test-db
make test
```

Если нужен точечный PHPUnit, сначала поднять Docker (`make up`), создать test DB
(`make test-db`) и запускать команду через `docker compose exec app` с
PostgreSQL `DATABASE_URL`, а не через `sqlite://`.

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

Локально для Doctrine/PostgreSQL требуется включенное расширение PHP `pdo_pgsql`;
в Docker оно уже входит в PHP runtime.

Для пересоздания локальной dev-БД использовать `make reset-db`: команда применяет migrations и запускает `make fixtures`. Если fixtures ещё не подключены, fixture-шаг является безопасным no-op.

