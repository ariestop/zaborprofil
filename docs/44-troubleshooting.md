# 44. Troubleshooting (dev)

Частые ошибки и быстрые решения для локальной разработки. Production runbook’и — в [37-runbooks](37-runbooks.md).

## Установка

### `composer install` падает с PHP version mismatch

```text
Your Composer dependencies require a PHP version >= 8.5
```

Проверьте `php -v`. Установите PHP 8.5+ или используйте Docker (`make composer-install`).

### `composer install` падает на `pdo_mysql`

```text
ext-pdo_mysql * is missing from your system
```

Native Linux: `apt install php8.5-mysql` (пакет содержит `pdo_mysql` и `mysqli`). На macOS (Homebrew) `pdo_mysql` входит в сборку PHP. Затем перезапустить PHP и проверить: `php -m | grep pdo_mysql`. В Docker (`make composer-install`) расширение уже установлено.

### `npm install` падает на `tailwindcss/typography`

См. [33-local-development](33-local-development.md). Решение — `npm install` после `node -v >= 25.9.0`.

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

Если вы запускаете не `make up`, а прямой `docker compose up -d`, не забудьте передать `.env.local`:

```bash
docker compose --env-file .env.local up -d
```

Иначе Compose возьмёт значения из `.env` (часто `HTTP_PORT=80`) и конфликт порта вернётся.

### Браузер не подключается к `127.0.0.1:8081` (Firefox: «не удаётся подключиться»)

Чаще всего Docker крутится на **другом** компьютере (удалённый dev-сервер, VM), а браузер — на вашем ПК: `127.0.0.1` в адресной строке — это loopback **ПК**, не сервера.

Решение: с ПК выполнить SSH port forwarding, например `ssh -N -L 8081:127.0.0.1:8081 user@server`, либо настроить `LocalForward` в `~/.ssh/config` / `autossh`. Подробно — [33-local-development](33-local-development.md) (раздел «Удалённый сервер»).

Если Docker и браузер на **одной** машине, проверьте `docker compose ps` (nginx **Up**) и что в `.env.local` порт совпадает с тем, что открываете в браузере.

### `channel X: open failed: connect failed: Connection refused` в SSH

Это ошибка SSH port forwarding: туннель поднят, но на удалённой стороне целевой порт не слушает (например, `nginx` не стартовал или слушает другой порт).

Проверьте на сервере:

```bash
docker compose --env-file .env.local ps
ss -tln '( sport = :8081 )'
```

Если переходите на доступ без SSH (например, через Tailscale), задайте в `.env.local` `HTTP_PORT=8081` (без `127.0.0.1:`), перезапустите стек и открывайте сайт по VPN-IP.

### `make migrate` упал — `Table '...' doesn't exist` / `Table '...' already exists`

DDL в MySQL не транзакционен: упавшая на середине миграция оставляет схему частично применённой (повторный запуск падает на `already exists`). Для локальной БД проще всего пересоздать её:

```bash
make reset-db
```

(уничтожит данные). Альтернатива — точечно посмотреть `migrations:status` и `SHOW CREATE TABLE` через `make db`.

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
php bin/console doctrine:schema:validate
```

Скажет, что не сходится между attributes и БД. Часто — забытый индекс, либо объект, созданный в миграции вручную и не выраженный в attributes (generated column, functional/FULLTEXT-индекс; такие объекты нужно описать listener-ом, как `PagePathActiveSchemaListener`, и задокументировать в [17-doctrine-and-database](17-doctrine-and-database.md) §13.1), либо устаревший кэш контейнера после смены listener-ов/`doctrine.yaml` (`cache:clear`), либо расхождение charset/collation таблицы с `default_table_options` (должно быть `utf8mb4` / `utf8mb4_0900_ai_ci`).

### `Class metadata not found`

Запустить `composer dump-autoload`.

### JSON: порядок ключей и пустые значения

В Doctrine используется тип `'json'`, в MySQL это нативная колонка `JSON` (бинарный формат). MySQL нормализует JSON-объекты: порядок ключей **не сохраняется** (сортировка по длине ключа, затем по значению), дубликаты ключей схлопываются; порядок элементов массивов сохраняется. Если тест сравнивает JSON как строку — сравнивать декодированные структуры (`json_decode`), а не текст. Пустой PHP-массив сериализуется как `[]`, а не `{}` — это нормально для `content`/`settings`.

### `SQLSTATE[HY000] [2002]` / `getaddrinfo for mysql failed`

Контейнер `mysql` не запущен или ещё не healthy. Проверить `docker compose ps mysql`, `docker compose logs mysql`, `make up`. Внутри Docker-сети хост БД — `mysql:3306`, с хост-машины — `127.0.0.1:${MYSQL_PORT}` (по умолчанию `13306`).

### `SQLSTATE[HY000] [1049] Unknown database 'zaborprofil_test'` / `1044 Access denied ... to database`

Тестовая БД не создана или у пользователя нет прав на неё: `make test-db` (создаёт `zaborprofil_test` и выдаёт права). Для dev-БД — `make reset-db`.

### `Incorrect string value` / «кракозябры» в кириллице

Соединение или таблица не в `utf8mb4`. Проверить `charset=utf8mb4` в `DATABASE_URL` и `default_table_options` в `config/packages/doctrine.yaml`; на уровне сервера — `docker/mysql/my.cnf` (`character-set-server = utf8mb4`). Не использовать `utf8`/`utf8mb3`.

### `Duplicate entry '...' for key 'uniq_...'` на «разных» значениях

Collation `utf8mb4_0900_ai_ci` регистро- и акцент-нечувствительна: `/About` = `/about`, `е` = `ё`. Это ожидаемо; если для колонки нужно различать регистр — задать `utf8mb4_bin` явно (см. [17-doctrine-and-database](17-doctrine-and-database.md) §2.2).

### `Specified key was too long; max key length is 3072 bytes` (1071)

Индекс по длинной `VARCHAR` в utf8mb4 (до 4 байт на символ) превышает лимит 3072 байта. Использовать prefix-индекс (`INDEX (col(191))`), хэш-колонку или уменьшить длину.

### `Deadlock found when trying to get lock` (1213) / `Lock wait timeout exceeded` (1205) / `MySQL server has gone away` (2006)

1213 — InnoDB откатил транзакцию, её нужно повторить целиком (см. [17-doctrine-and-database](17-doctrine-and-database.md) §7.3). 1205 — долгая конкурирующая транзакция: `SELECT * FROM information_schema.INNODB_TRX`, `SHOW FULL PROCESSLIST`. 2006 — соединение закрыто сервером по `wait_timeout` (долгоживущий `messenger:consume` — перезапускать по `--time-limit`).

### `Cannot add foreign key constraint` (1215) / `Referencing column and referenced column are incompatible` (3780)

Тип, длина, signed/unsigned или collation FK-колонки не совпадают с родительским ключом. Для ULID обе стороны — `BINARY(16)`.

### Идентификатор в консоли `mysql` выглядит как «мусор»

ULID хранится как `BINARY(16)`. Для просмотра — `SELECT HEX(id) ...`, для поиска — `WHERE id = UNHEX('<hex>')` (hex получить из `Ulid::fromString(...)->toHex()`).

## Тесты

### PHPUnit не может подключиться к test database

Тесты должны идти через MySQL из Docker Compose:

```bash
make up
make test-db
make test
```

Если подключение падает, проверить `docker compose ps mysql`, `make test-db`
и `DATABASE_URL` из `.env.test`. SQLite для локальных тестов запрещён.

### Functional test падает с `403 CSRF Invalid`

В тестовой `security.yaml` `login_throttling: false` уже есть. Для CSRF — использовать `WebTestCase` с правильным CSRF token (или mock через `enableProfiler`).

### Tests падают only in CI

Скорее всего из-за различий env, timezone, версии MySQL, `sql_mode` или collation. Проверить
`tests/bootstrap.php`, `phpunit.xml`, `.env.test.ci` и CI service `mysql` (`mysql:8.4`).

## Frontend

### `tsc --noEmit` падает на типы

`npm install`, затем перезапустить TS-сервер. Если ошибка корректная — исправить типы.

### Vite dev server недоступен

`make npm-dev` — следить, что порт 5173 свободен. `ViteAssetExtension` определит автоматически.

### В админке нет кнопки «Перекомпилировать» (виджет «Сборка интерфейса»)

Пересборка из админки включена только при `APP_DEBUG=1`, то есть в локальной разработке. На staging и production фронтенд собирает CI, поэтому API `/admin/api/system/assets/build` отвечает `404 ASSET_BUILD_DISABLED`, а виджет скрыт. Принудительно включить (нужен Node.js в PHP-FPM): `APP_ASSET_BUILD_ENABLED=1` в `.env.local`. Свою команду сборки можно задать через `APP_ASSET_BUILD_COMMAND`.

### В админке «Перекомпилировать» падает с `npm ERR! EACCES ... /var/www/html/node_modules/...`

Симптом в логе:

```text
npm ERR! code EACCES
npm ERR! syscall mkdir
npm ERR! path /var/www/html/node_modules/...
```

Обычно это несовпадение прав в `node_modules` между контейнерами `node` и `app`.

Быстрое решение:

```bash
make down
make up
```

Запуск через `make` гарантирует корректный `--env-file .env.local` и штатные docker-compose override-настройки для `node_modules`.

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
- CSRF token не передан в `X-CSRF-Token` → проверить React API-клиент.

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
docker volume rm zaborprofil_mysql_data zaborprofil_uploads_data
make build
make up
make composer-install
make npm-install
make migrate
make npm-build
```

Это полностью обнулит local environment (включая данные MySQL и загруженные файлы). При первом старте пустого тома `mysql_data` скрипт `docker/mysql/init.sh` заново создаст БД `zaborprofil` и `zaborprofil_test`.

## Связанные документы

- [33-local-development](33-local-development.md)
- [37-runbooks](37-runbooks.md)
- [33-local-development](33-local-development.md)
- [32-docker-architecture](32-docker-architecture.md)
