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
- Vue 3 для админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker используется только для local development. Staging и production должны оставаться native VPS stack: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based release deploy.

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, value objects, infrastructure adapters и UI entrypoints.
- `src/Module/` — модули модульного монолита.
- `templates/` — Twig-шаблоны публичного сайта и админки.
- `assets/site/` — frontend публичного сайта.
- `assets/admin/` — Vue 3 entrypoint админ-панели.
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

