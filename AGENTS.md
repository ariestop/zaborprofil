# AGENTS.md

Контекст для Cursor, Codex и других AI-агентов, работающих с проектом `zaborprofil`.

## Назначение проекта

Это новый production-ready Symfony CMS Engine для корпоративного сайта `zaborprofil.ru`.
Он заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

## Текущий стек

- PHP `>=8.4`
- Symfony `8.x`
- PostgreSQL `>=18`
- Redis
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig для публичного SSR
- Tailwind CSS, Vite
- Vue 3 для будущей админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker не используется. Проект рассчитан на VPS: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based deploy.

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, value objects, infrastructure adapters и UI entrypoints.
- `src/Module/` — модули модульного монолита.
- `templates/` — Twig-шаблоны публичного сайта и админки.
- `assets/site/` — frontend публичного сайта.
- `assets/admin/` — Vue 3 entrypoint будущей админки.
- `docs/` — документация на русском языке.

## Архитектурные правила

- Соблюдать Clean Architecture и Modular Monolith.
- Контроллеры должны быть тонкими: request -> DTO/валидация -> use case -> response.
- Не размещать бизнес-логику в контроллерах.
- Не превращать Doctrine Entity в god object.
- Не добавлять WordPress importer, shortcode parser, EAV без необходимости, Docker или EasyAdmin как основную админку.
- Публичный сайт должен оставаться SSR на Symfony + Twig, не SPA.
- Все PHP-файлы должны использовать `declare(strict_types=1)`.
- Документация пишется на русском языке.

## Проверки

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

Локально для Doctrine/PostgreSQL требуется включенное расширение PHP `pdo_pgsql`.

