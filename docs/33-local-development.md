# 33. Local development

См. также [LOCAL_DOCKER.md](LOCAL_DOCKER.md), [INSTALL.md](legacy/INSTALL.md).

## Требования

- Docker Desktop / Docker Engine + Compose v2.
- Git.
- ≥ 4 GB RAM выделено Docker’у.
- Свободные порты на хосте: `80`, `15432`, `16379`, `8025`, `8080`, `5173`. Переопределяются через `.env.local`.

### Windows 10/11

На Windows используйте Docker Desktop с WSL2 backend. В Docker Desktop включите интеграцию с Ubuntu-дистрибутивом, в котором лежит проект, и запускайте все команды из WSL terminal, а не из PowerShell/CMD.

Рабочая копия должна лежать внутри Linux-файловой системы WSL:

```bash
cd ~/zaborprofil
```

Путь вида `\\wsl.localhost\Ubuntu\home\<user>\zaborprofil` можно открывать в Cursor/Explorer, но команды `make`, `docker compose`, `composer` и `npm` выполняются из Ubuntu/WSL. Не храните проект на `C:\projects\...`: это замедляет bind mounts, `node_modules`, `vendor` и работу Docker.

Если `make` не установлен:

```bash
sudo apt update
sudo apt install -y make
```

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

На новом Windows/WSL окружении можно использовать короткий вариант:

```bash
cp .env.local.example .env.local
make init
make health
```

Если порт `80` занят IIS, Skype, OSPanel или другим локальным сервером, задайте другой порт в `.env.local`:

```dotenv
HTTP_PORT=8081
SITE_URL="http://localhost:8081"
DEFAULT_URI="http://localhost:8081"
```

Затем примените настройки:

```bash
make up
make health
```

Для получения единого dev-состояния БД после клонирования используйте `make reset-db`: команда пересоздаёт локальную БД, применяет migrations и запускает `make fixtures`.

После запуска:

- `http://localhost` — публичный сайт.
- `http://localhost/admin/login` — логин админки (создать пользователя — см. ниже).
- `http://localhost/health` — healthcheck.
- `http://localhost:8025` — Mailpit.
- `http://localhost:8080` — Adminer.

Если вы поменяли `HTTP_PORT`, открывайте сайт по значению `SITE_URL`, например `http://localhost:8081`.

В `HTTP_PORT` допустим формат с привязкой только к loopback хоста (без доступа из LAN): `127.0.0.1:8081`. Тогда сайт открывается только на той машине, где запущен Docker.

## Удалённый сервер: браузер не на той же машине, что и Docker

Типичный случай: код и `docker compose` на Linux-сервере (или VM), а Firefox/Chrome — на ноутбуке. Адрес `http://127.0.0.1:8081` в браузере ноутбука указывает на **локальный** loopback ноутбука, а не на сервер, поэтому соединение отклоняется, даже если на сервере nginx слушает `127.0.0.1:8081`.

### Вариант A: SSH-туннель (быстрый и временный)

**Разовый проброс портов** (с ноутбука; подставьте пользователя и хост):

```bash
ssh -N -L 8081:127.0.0.1:8081 user@dev-server.example
```

Пока сессия SSH открыта, на ноутбуке доступны те же URL, что и на сервере, например `http://127.0.0.1:8081/`. При необходимости добавьте цепочку `-L` для других портов из `.env.local` (часто: `5173` — Vite, `8025` — Mailpit, `8080` — Adminer, `15432` — Postgres).

**Постоянная настройка в `~/.ssh/config`** на ноутбуке:

```sshconfig
Host zaborprofil-dev
    HostName dev-server.example
    User user
    LocalForward 8081 127.0.0.1:8081
    LocalForward 5173 127.0.0.1:5173
    LocalForward 8025 127.0.0.1:8025
    LocalForward 8080 127.0.0.1:8080
    ServerAliveInterval 30
    ServerAliveCountMax 3
```

Запуск только туннеля (без интерактивной shell на сервере):

```bash
ssh -N zaborprofil-dev
```

**Автопереподключение:** установите `autossh` и используйте, например, `autossh -M 0 -f -N zaborprofil-dev` (те же `LocalForward` в `Host`).

**Автозапуск при входе в сессию:** можно оформить `systemd --user` unit с `ExecStart=/usr/bin/autossh -M 0 -N zaborprofil-dev` и `Restart=always` (см. примеры в man `systemd.service`).

Альтернатива: встроенный port forwarding в Cursor/VS Code (**Ports**), если вы подключены к удалённому workspace по Remote SSH.

### Вариант B: доступ без SSH через Tailscale/VPN

Если нужна постоянная работа без SSH-туннеля, подключите сервер и ноутбук в одну VPN-сеть (например, Tailscale) и откройте HTTP-порт наружу:

```dotenv
HTTP_PORT=8081
SITE_URL=http://<tailscale-ip-сервера>:8081
DEFAULT_URI=http://<tailscale-ip-сервера>:8081
```

Важно: в `HTTP_PORT` не должно быть `127.0.0.1:...`, иначе порт будет доступен только локально на сервере.

Проверка после перезапуска:

```bash
docker compose --env-file .env.local ps
ss -tln '( sport = :8081 )'
```

Ожидается bind на `0.0.0.0:8081` или `[::]:8081`.

Для безопасности оставляйте инфраструктурные порты (`POSTGRES_PORT`, `REDIS_PORT`, `MAILPIT_PORT`, `ADMINER_PORT`) на `127.0.0.1:...`.

## Создание администратора

Целевое: консольная команда `app:user:create-admin` (пока отсутствует). Сейчас: сгенерировать bcrypt-хеш и вставить строку в `admin_users` (тип `id` в PostgreSQL — **UUID**, соответствующий ULID в PHP; колонки: `email`, `roles` jsonb, `password_hash`, `active`, `created_at`, `updated_at`).

Сгенерировать хеш пароля (из корня проекта, внутри app-контейнера при Docker):

```bash
docker compose exec app php bin/console security:hash-password 'your-password' --no-interaction
```

Получить UUID для нового ULID (подставьте свой ULID из `Symfony\Component\Uid\Ulid` или сгенерируйте новый):

```bash
docker compose exec app php -r 'require "vendor/autoload.php"; echo (new Symfony\Component\Uid\Ulid())->toRfc4122();'
```

Далее `INSERT` через Adminer/psql с полученными `id` (uuid), `email`, `roles` (например `'["ROLE_ADMIN"]'::jsonb`), `password_hash` и метками времени. Не копируйте чужие примеры с устаревшими именами колонок (`password`, `is_active`): актуальная схема — в миграции `Version20260501000100` и entity `AdminUser`.

## Ежедневные команды

```bash
make init                     # build + up + install deps + migrate + build + smoke
make up                       # старт сервисов
make shell                    # шелл в php-контейнере
make logs                     # tail логов всех сервисов
make migrate                  # применить миграции
make migration                # сгенерировать diff-миграцию
make reset-db                 # drop + create + migrate + fixtures
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
| Браузер: «не удаётся подключиться» к `127.0.0.1:8081` | Docker на **другом** хосте, чем Firefox, или туннель закрыт | SSH `-L` / `LocalForward` в `~/.ssh/config`, см. раздел «Удалённый сервер» выше |
| Админка: сборка ассетов падает с `npm ERR! EACCES ... /node_modules/...` | сервис `app` не имеет прав на `node_modules` | запустите стек через `make up` (подхватит `.env.local` и корректные volume/права), затем повторите «Перекомпилировать» |

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
- [44-troubleshooting](44-troubleshooting.md)
- [47-dev-database-state](47-dev-database-state.md)
- [INSTALL.md](legacy/INSTALL.md)
- [LOCAL_DOCKER.md](LOCAL_DOCKER.md)
