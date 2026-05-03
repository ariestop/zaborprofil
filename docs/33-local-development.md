# 33. Local development

См. также [LOCAL_DOCKER.md](legacy/LOCAL_DOCKER.md), [INSTALL.md](legacy/INSTALL.md).

## Требования

- Docker Desktop / Docker Engine + Compose v2.
- Git.
- ≥ 4 GB RAM выделено Docker’у.
- Свободные порты на хосте: `80`, `15432`, `16379`, `8025`, `8080`, `5173`. Переопределяются через `.env.local`.

Native (без Docker, опционально, для OSPanel/диагностики):

- PHP 8.5 + extensions `ctype, iconv, intl, mbstring, pdo_pgsql, redis`.
- Composer 2.
- Node.js 25.9.0 / npm 11.12.1.
- PostgreSQL 18.
- Redis 8.

## Первый запуск (Docker)

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

После запуска:

- `http://localhost` — публичный сайт.
- `http://localhost/admin/login` — логин админки (создать пользователя — см. ниже).
- `http://localhost/health` — healthcheck.
- `http://localhost:8025` — Mailpit.
- `http://localhost:8080` — Adminer.

## Создание администратора

Целевое: console command `app:user:create-admin` (пока создаётся напрямую через миграцию-фикстуру). Текущий способ — SQL через Adminer или `psql`:

```sql
INSERT INTO admin_users (id, email, password, roles, is_active, created_at, updated_at)
VALUES (
  '01HZ0000000000000000000000',
  'admin@example.com',
  '<bcrypt-hash>',
  '["ROLE_SUPER_ADMIN"]',
  true,
  now(), now()
);
```

Для генерации hash:

```bash
php bin/console security:hash-password
```

## Ежедневные команды

```bash
make init                     # build + up + install deps + migrate + build + smoke
make up                       # старт сервисов
make shell                    # шелл в php-контейнере
make logs                     # tail логов всех сервисов
make migrate                  # применить миграции
make migration                # сгенерировать diff-миграцию
make reset-db                 # drop + create + migrate
make test                     # phpunit
make quality                  # validate + syntax + cs + phpstan + rector + db/schema/twig/container + phpunit + smoke + npm build
make smoke                    # app:smoke:test в test-env
make cache-clear              # cache:clear внутри app
make composer-install
make npm-install
make npm-dev                  # vite dev server
make npm-build                # vite build
make health                   # curl SITE_URL/health
make db                       # psql внутри postgres
make redis                    # redis-cli
make down                     # остановить
```

## Native запуск (без Docker)

```bash
composer install
npm install
npm run build
php bin/console doctrine:migrations:migrate
```

`.env.local` для native:

```dotenv
APP_ENV=dev
APP_SECRET=change-this-secret
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/zaborprofil?serverVersion=18&charset=utf8"
REDIS_URL="redis://127.0.0.1:6379"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
SITE_URL=http://localhost:8000
DEFAULT_URI=http://localhost:8000
```

Запуск через Symfony local server (`symfony server:start`) — допустимо для отладки.

## Frontend dev mode

```bash
make npm-dev          # vite на :5173
```

`ViteAssetExtension` отдаёт ссылки на `:5173` пока активен dev server и manifest отсутствует / устарел. Hot Module Replacement работает для admin SPA.

## IDE

- PhpStorm: автоматически детектит Symfony, Doctrine, Twig.
- Cursor / VS Code:
  - PHP Intelephense / Phpactor;
  - Twig syntax;
  - ESLint / TypeScript;
  - Volar для Vue.

## Pre-commit (целевое)

Добавить `pre-commit`:

- `composer check:cs` (или `php-cs-fixer fix --dry-run`);
- `composer check:phpstan`;
- `npm run typecheck`.

## Typical local issues

| Симптом | Причина | Решение |
|---|---|---|
| `Cannot find module '@tailwindcss/typography'` | нет `node_modules/` | `make npm-install` или `npm install` |
| 502 на `http://localhost` | php контейнер ещё не поднялся | `make logs`, дождаться FPM ready |
| `permission denied` на `var/cache` | mount-перезапись прав | `make shell` → `chown -R www-data:www-data var` или `make build` |
| Postgres конфликтует с локальным | порт `5432` занят | `POSTGRES_PORT=15432` уже в default; либо ставите свой |
| HMR Vite не работает | dev server не запущен | `make npm-dev` |
| Тесты падают на CSRF | нет `.env.test.local` | используйте phpunit с дефолтным `.env.test`, для CI используется `.env.test.ci` |
| Время в логах не московское | `php.ini` timezone | `docker/php/php.ini` → `date.timezone` |

## Проверка установки

```bash
docker compose ps
make health
curl -fsS http://localhost/health
php bin/console about
php bin/console router:match /health
vendor/bin/phpunit
```

## Связанные документы

- [32-docker-architecture](32-docker-architecture.md)
- [27-config-and-env](27-config-and-env.md)
- [INSTALL.md](legacy/INSTALL.md)
- [LOCAL_DOCKER.md](legacy/LOCAL_DOCKER.md)
