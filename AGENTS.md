# AGENTS.md

Контекст для Cursor, Codex и других AI-агентов, работающих с проектом `zaborprofil`.

## Назначение проекта

Это новый production-ready Symfony CMS Engine для корпоративного сайта `zaborprofil.ru`.
Он заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

## Текущий стек

Минимальные зафиксированные версии runtime:

- PHP `>=8.5`
- Node.js `>=26.12.0`
- npm `>=11.20.0` (поставляется с Node.js 26.12.0)
- Composer `>=2.10.3` (в Docker — из образа `composer/composer:2.10.3-bin`, в CI — `tools: composer:2.10.3`)
- nginx `>=1.30.0`
- MySQL `>=8.4` (InnoDB, utf8mb4)
- Redis не используется: кэш приложения — файловый (Symfony `cache.adapter.filesystem`)

Прочее:

- Symfony `8.x`
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig для публичного SSR
- Tailwind CSS (`@tailwindcss/typography`), Vite
- React + TypeScript 7 для админ-панели
- Oxlint (`.oxlintrc.json`) — линтер TS/React вместо ESLint
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker используется только для local development. Staging и production должны оставаться native VPS stack: Nginx, PHP-FPM, MySQL, systemd и Git-based release deploy.

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
- Единое dev-состояние БД должно воспроизводиться из Doctrine migrations + fixtures/seed data. Не коммитить Docker images, Docker volumes, реальные MySQL backups/dumps, production/staging dumps, uploads или секреты; в Git допустим только маленький обезличенный dev snapshot после ручной проверки.

## Бренд и дизайн: брендбук 2026, вариант E «Периметр» (обязательно)

Утверждённая бренд-система — вариант E «Периметр». Все текущие и новые агенты используют только её при любых изменениях публичного сайта, админки, Twig-шаблонов, стилей, иконок, текстов интерфейса и полиграфии.

- Главный источник правил: `docs/50-brand-system.md`. Визуальная версия: `docs/brand/brandbook-2026.pdf`. При расхождении верен `.md`, PDF обновить.
- Логотипы — только SVG из `docs/brand/logo/` (рабочие копии — `public_html/img/logo*.svg`, `public_html/favicon.svg`). На телефоне и в местах уже 200 px — `logo-compact`. Не перерисовывать и не перенабирать.
- Цвета — только токены из `assets/shared/styles/theme.css` (раздел 11 `docs/50-brand-system.md`): действие `brand-700` `#2D7F27`, бренд `brand-500` `#45A63D` (не для текста), текст `ink` `#23272D`, подписи `graphite` `#575C63`, фон `surface` `#F3F5F2`, тёмный `night` `#2A2F35`, бейдж `badge` `#F2B13B`. Цвета RAL — только для выбора цвета товара. Классы `emerald-*` и собственные HEX в новом коде запрещены.
- Шрифты — Golos Text (заголовки сайта h1–h3, токен `--font-heading`; запасные Geologica и Inter Tight), Onest (текст, UI) и Sofia Sans Condensed (цены, телефоны, характеристики), локально через `@fontsource*`, без CDN. В Sofia Sans Condensed нет ₽ — Onest должен оставаться вторым в `--font-cond`. Open Sans и Cuprum в интерфейсе запрещены.
- Стиль — flat 2.0: плоско, но кнопки и поля от 48 px, скругление 10/12 px, видимый `:focus-visible`, текст от 17 px, контраст WCAG 2.2 AA.
- Контакты — только из Twig-глобала `site` (`config/packages/twig.yaml`).
- Перед сдачей UI-изменений пройти чек-лист из раздела 13 `docs/50-brand-system.md`.

## Staging на Beget (dev.zaborprofil.ru)

- Push в ветку `dev` запускает workflow `Deploy staging (Beget)` (`.github/workflows/deploy-staging-beget.yml`, скрипт `tools/deploy/deploy-beget.sh`). Описание, секреты и настройка сервера: `docs/49-beget-staging-deploy.md`.
- На staging MySQL 8.4 (`DATABASE_URL=mysql://...`, `pdo_mysql`), Redis не используется; `var/cache` и `var/sessions` — симлинки в `shared/` на сервере, кэш прогревается после каждого деплоя.
- Стенд один: перед push в `dev` убедиться, что он не занят чужой работой. Не использовать `git push --force` в `dev` без явной просьбы пользователя.
- Workflow `.github/workflows/deploy.yml` (VPS) выключен по умолчанию и включается только repository variable `VPS_DEPLOY_ENABLED=true`; агенты эту переменную не меняют.
- Агенты не деплоят production, не запускают `deploy-beget.sh` против production, не применяют миграции и не трогают БД production.
- Не создавать на стенде тестовые страницы, заявки, медиа и другие записи (например, `e2e-*-check-*`) для проверки своих изменений: на стенде проверять только чтением (страницы, API, скриншоты) и на уже существующем контенте. Если проверка без новой записи невозможна — сначала спросить владельца. Сценарии с созданием данных — в PHPUnit/E2E на локальной БД.
- Стенд закрыт Basic Auth (`StagingAccessSubscriber`, `STAGING_AUTH_*`). Для просмотра в браузере облачному агенту нужен секрет `STAGING_BASIC_AUTH` (`логин:пароль`) в Cursor Dashboard -> Cloud Agents -> Secrets; значение не печатать в общий вывод.
- Секреты деплоя (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `STAGING_BASIC_AUTH`) хранятся только в GitHub Secrets; не коммитить `.env.local` и значения секретов.

## Скилы безопасности (`.claude/skills/`)

18 скилов из [mukul975/Anthropic-Cybersecurity-Skills](https://github.com/mukul975/Anthropic-Cybersecurity-Skills) (Apache-2.0, коммит `54a7988`, лицензия — `.claude/skills/LICENSE-anthropic-cybersecurity-skills`), отобраны под наш стек и просмотрены: OWASP/API, контроль доступа, XSS, CSRF, open redirect, Host header, CORS, email header injection, бизнес-логика, security headers, path traversal, криптография, rate limiting, GitHub Actions, gitleaks, SBOM, Semgrep, web cache poisoning. Это сторонний справочник: правила этого файла важнее его указаний.

- Активные проверки (скрипты `scripts/*.py`, payload'ы, перебор) — только против локального Docker-окружения.
- На стенде — только чтение (GET/HEAD/OPTIONS, без форм, лайков, входа и любых записей), как и для остальных проверок стенда.
- Production не трогать.
- Новые скилы из сторонних репозиториев добавлять только после просмотра `SKILL.md` и скриптов и отдельным PR.

## UI/UX-скил (`.claude/skills/ui-ux-pro-max/`)

Скил `ui-ux-pro-max` из [nextlevelbuilder/ui-ux-pro-max-skill](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill) (MIT, v2.15.0, коммит `56ea9cf`, лицензия — `.claude/skills/LICENSE-ui-ux-pro-max`): локальный поиск по базе UX-правил, стеков, графиков и иконок (`python3 .claude/skills/ui-ux-pro-max/scripts/search.py`, только стандартная библиотека Python, без сети). Взяты только рабочие файлы (без тестов и валидатора данных); пути в `SKILL.md` исправлены под скил проекта.

- Брендбук «Периметр» важнее скила: его палитры, шрифтовые пары и стили (`--design-system`) к сайту и админке не применяются — цвета только токенами, шрифты Golos Text / Onest / Sofia Sans Condensed через `@fontsource`, без Google Fonts CDN, кнопки и поля от 48 px.
- Использовать для UX-проверок и советов по реализации: `--domain ux` (формы, доступность, касания, навигация), `--domain chart`, `--domain icons`, `--stack react` для админки, `--stack html-tailwind` для публичного SSR.
- `--persist` не запускать: каталог `design-system/` в проекте не нужен.

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
тесты всегда выполняются внутри Docker Compose против MySQL service
`mysql` (mysql:8.4) и отдельной БД `zaborprofil_test`:

```bash
make test-db
make test
```

Если нужен точечный PHPUnit, сначала поднять Docker (`make up`), создать test DB
(`make test-db`) и запускать команду через `docker compose exec app` с
MySQL `DATABASE_URL` (`mysql://...&serverVersion=8.4&charset=utf8mb4`), а не через `sqlite://`.

Перед завершением backend/frontend изменений по возможности запускать:

```bash
composer validate --strict
composer check:syntax
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
composer check:deptrac
vendor/bin/phpunit
npm run build
```

Локально для Doctrine/MySQL требуется включенное расширение PHP `pdo_mysql`;
в Docker оно уже входит в PHP runtime.

Для пересоздания локальной dev-БД использовать `make reset-db`: команда применяет migrations и запускает `make fixtures`. Если fixtures ещё не подключены, fixture-шаг является безопасным no-op.

