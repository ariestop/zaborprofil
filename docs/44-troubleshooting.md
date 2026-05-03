# 44. Troubleshooting (dev)

Частые ошибки и быстрые решения для локальной разработки. Production runbook’и — в [37-runbooks](37-runbooks.md).

## Установка

### `composer install` падает с PHP version mismatch

```text
Your Composer dependencies require a PHP version >= 8.5
```

Проверьте `php -v`. Установите PHP 8.5+ или используйте Docker (`make composer-install`).

### `composer install` падает на `pdo_pgsql`

```text
ext-pdo_pgsql * is missing from your system
```

Native macOS / Linux: `brew install postgresql@18` / `apt install php8.5-pgsql`. Затем перезапустить PHP.

### `npm install` падает на `tailwindcss/typography`

См. [INSTALL.md](legacy/INSTALL.md). Решение — `npm install` после `node -v >= 25.9.0`.

### `Cannot find module '@tailwindcss/typography'` в IDE

`npm install`, затем `Cmd+Shift+P → TypeScript: Restart TS Server`.

## Docker

### `make up` падает на `bind: address already in use`

Порт 80 занят. В `.env.local`:

```dotenv
HTTP_PORT=8081
SITE_URL=http://localhost:8081
DEFAULT_URI=http://localhost:8081
```

### `make migrate` упал — `relation "..." does not exist`

```bash
make reset-db
```

(уничтожит данные). Альтернатива — точечно посмотреть `migrations:status`.

### Файлы созданы под root в Docker, локально не редактируются

Включить `make build` пересобрал php image; альтернатива — `chown` на хосте после `make shell`.

## Symfony

### Кеш сломан после переключения веток

```bash
make cache-clear
# или native
php bin/console cache:clear
```

### `MissingMandatoryParameterException: APP_SECRET`

`.env.local` не создан или пуст. `cp .env.local.example .env.local`.

### `lint:container` падает на новый сервис

Проверьте `services.yaml` — нет ли несовпадения типов аргумента и autowire.

### `lint:twig` падает

Прочитайте сообщение, проверьте отсутствие `{% endblock %}`/`{% endif %}`/`{% endfor %}`.

## Doctrine

### `doctrine:migrations:diff` создаёт пустой файл

Изменений в attributes не было. Удалите файл.

### `doctrine:migrations:diff` показывает изменения, которых не делали

Возможно, naming strategy не совпадает с актуальной БД. Проверить `naming_strategy`.

### `doctrine:schema:validate` ошибочный

```bash
php bin/console doctrine:schema:validate --skip-sync
```

Скажет, что не сходится между attributes и БД. Часто — забытый индекс или partial index.

### `Class metadata not found`

Запустить `composer dump-autoload`.

### Postgres jsonb vs array

В Doctrine используется `'json'` тип; PostgreSQL автоматически кладёт в `jsonb`.

## Тесты

### `KernelTestCase` падает с `database is locked`

SQLite не любит параллельные тесты. Использовать `--processes=1` или Postgres (через `.env.test.ci`).

### Functional test падает с `403 CSRF Invalid`

В тестовой `security.yaml` `login_throttling: false` уже есть. Для CSRF — использовать `WebTestCase` с правильным CSRF token (или mock через `enableProfiler`).

### Tests падают only in CI

Скорее всего из-за БД (Postgres vs SQLite) или timezone. Проверить `tests/bootstrap.php`, `phpunit.xml`.

## Frontend

### `vue-tsc --noEmit` падает на типы

`npm install`, затем перезапустить TS-сервер. Если ошибка корректная — исправить типы.

### Vite dev server недоступен

`make npm-dev` — следить, что порт 5173 свободен. `ViteAssetExtension` определит автоматически.

### Manifest не подхватывается

```bash
make npm-build
```

Проверить `public_html/build/.vite/manifest.json`.

## Admin / Auth

### Логин не работает: `Invalid CSRF token`

В `templates/admin/security/login.html.twig` должен быть `<input type="hidden" name="_csrf_token" ...>`. Если изменили — вернуть.

### После создания пользователя не пускает

Проверить `is_active = true` у `admin_users`.

### `403` на admin API

- Origin/Referer не совпадает с `SITE_URL` → проверить `.env.local`.
- CSRF token не передан в `X-CSRF-Token` → проверить Vue-клиент.

## Email

### Не приходят email’ы локально

Mailpit на `:8025`. `MAILER_DSN=smtp://mailpit:1025` в `.env.local`. Открыть `http://localhost:8025`.

## Logs

### `var/log` забит, диск полон

```bash
> var/log/app.log     # truncate
make cache-clear
```

В production — logrotate.

### Логи не пишутся

Проверить права: `var/log` writable для www-data.

## Когда ничего не помогает

```bash
make down
docker volume rm zaborprofil_postgres_data zaborprofil_redis_data zaborprofil_uploads_data
make build
make up
make composer-install
make npm-install
make migrate
make npm-build
```

Это полностью обнулит local environment.

## Связанные документы

- [33-local-development](33-local-development.md)
- [37-runbooks](37-runbooks.md)
- [INSTALL.md](legacy/INSTALL.md)
- [LOCAL_DOCKER.md](legacy/LOCAL_DOCKER.md)
