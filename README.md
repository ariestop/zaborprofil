# Zaborprofil CMS Engine

Новый Symfony CMS Engine для корпоративного сайта [zaborprofil.ru](https://zaborprofil.ru).

Проект заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через кастомную админ-панель.

## Стек

Минимальные зафиксированные версии runtime:

- PHP `>=8.5`
- Node.js `>=25.9.0`
- npm `>=11.12.1` (входит в состав Node.js 25.9.0)
- nginx `>=1.30.0`
- PostgreSQL `>=18`
- Redis `>=8`

Прочее:

- Symfony `8.x`
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig
- Tailwind CSS (`@tailwindcss/typography`)
- Vite
- Vue 3 для админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker используется только для локальной разработки. Staging и production разворачиваются на VPS без Docker: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based release deploy.

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, инфраструктура и UI-адаптеры.
- `src/Module/` — модули модульного монолита.
- `assets/site/` — frontend публичного сайта.
- `assets/admin/` — Vue 3 SPA админ-панели.
- `templates/` — Twig-шаблоны.
- `docs/` — документация на русском языке.

## Реализовано

- Symfony 8 CMS-каркас с Docker local development и native VPS deploy scripts.
- Авторизация админки через Symfony Security, RBAC и `AdminPermissionVoter`.
- Health-check `/health`, `/health/live`, `/health/ready` и diagnostics API.
- Content Engine: `Page`, `PageBlock`, enum статусов/типов, Doctrine repositories, Admin API.
- Публичный Twig renderer опубликованных страниц по `Page.path` с SEO metadata и `cache.public_page`.
- Preview links для черновиков с `noindex,nofollow`.
- SEO base: redirects, sitemap index/chunks, robots manager, canonical guard, OpenGraph, JSON-LD, SEO audit и pre-publish checklist.
- Media Library с безопасной загрузкой, re-encode изображений и WebP/AVIF variants.
- Управляемые меню `header`, `footer`, `service`, breadcrumbs и JSON-LD `BreadcrumbList`.
- Lead pipeline: публичная lead-форма, anti-spam, consent snapshot, email/Telegram notifications.
- Settings, Maintenance Mode, Audit Log, Business Events и Vue admin shell.
- Dev/QA readiness: `make init`, расширенный `make quality`, `app:smoke:test`.
- Unit, Integration и Functional тесты для ключевых CMS-сценариев.

## Быстрый старт через Docker

```bash
make init
make health
```

Откройте:

- `http://localhost`
- `http://localhost/admin`
- `http://localhost/health`
- `http://localhost:8025` — Mailpit
- `http://localhost:8080` — Adminer

Подробнее: `docs/LOCAL_DOCKER.md`.

## Единое dev-состояние БД

Новый ПК разработки должен получать одинаковую структуру и базовые данные через Git: Doctrine migrations + dev fixtures/seed data. Docker images, Docker volumes и реальные backup-файлы БД в Git не хранятся.

Для пересоздания локальной БД используйте:

```bash
make reset-db
```

Команда удаляет локальную БД, создаёт её заново, применяет миграции и запускает `make fixtures`. Если fixtures ещё не подключены, шаг завершится информационным сообщением без ошибки.

Если нужен общий набор демонстрационных данных, храните в Git только маленький обезличенный dev snapshot/seed после ручной проверки. Production/staging backups, секреты, персональные данные и Docker volumes в Git запрещены. Подробнее: `docs/47-dev-database-state.md`.

## Native запуск без Docker

```bash
composer install
npm install
npm run build
php bin/console doctrine:migrations:migrate
```

Проверка:

```bash
php bin/console about
php bin/console router:match /health
vendor/bin/phpunit
```

Откройте:

- `/health`
- `/admin/login`

## Deploy

Staging и production деплоятся без Docker через release-based структуру:

```text
/var/www/zaborprofil/
├── releases/
├── shared/
└── current -> releases/<timestamp>
```

Production deploy разрешен только после успешного staging deploy, с backup перед миграциями и health-check после переключения релиза.

Документация:

- `docs/STAGING.md`
- `docs/PRODUCTION.md`
- `docs/DEPLOY.md`
- `docs/CI_CD.md`

## Content Engine

Admin API доступен под `/admin/api/content/...` и защищен текущим admin firewall.

Публичный URL открывает опубликованную страницу по `Page.path`. Черновики и архивные страницы публично не показываются и возвращают `404`.

Подробнее: `docs/CONTENT_ENGINE.md`.

## Дальше

W0-W11 из `docs/FEATURES_PLAN.md` реализованы. Дальнейшие направления: staging smoke deploy, restore rehearsal, monitoring/release readiness и следующий крупный продуктовый этап `Catalog / Product / Variant`. Канонический порядок работ зафиксирован в `docs/45-roadmap-and-extension-points.md` и `docs/FEATURES_PLAN.md`.
