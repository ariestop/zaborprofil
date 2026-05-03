# Zaborprofil CMS Engine

Новый Symfony CMS Engine для корпоративного сайта [zaborprofil.ru](https://zaborprofil.ru).

Проект заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

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
- `assets/admin/` — Vue entrypoint будущей админки.
- `templates/` — Twig-шаблоны.
- `docs/` — документация на русском языке.

## Реализовано

- Symfony 8 CMS-каркас с Docker local development и native VPS deploy scripts.
- Авторизация админки через Symfony Security, RBAC и `AdminPermissionVoter`.
- Health-check `/health`, `/health/live`, `/health/ready` и diagnostics API.
- Content Engine: `Page`, `PageBlock`, enum статусов/типов, Doctrine repositories, Admin API.
- Публичный Twig renderer опубликованных страниц по `Page.path` с SEO metadata и `cache.public_page`.
- SEO base: redirects, sitemap, robots, canonical, OpenGraph, JSON-LD.
- Settings, Maintenance Mode, Audit Log, Business Events и базовый Vue admin shell.
- Unit, Integration и Functional тесты для ключевых CMS-сценариев.

## Быстрый старт через Docker

```bash
make build
make up
make composer-install
make npm-install
make migrate
make npm-build
make health
```

Откройте:

- `http://localhost`
- `http://localhost/admin`
- `http://localhost/health`
- `http://localhost:8025` — Mailpit
- `http://localhost:8080` — Adminer

Подробнее: `docs/LOCAL_DOCKER.md`.

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

## Следующий этап

Следующий этап — довести launch-ready контентный сайт: preview для черновиков, Vue UI страниц/блоков, Media Library, управляемые меню, Lead-формы и pre-launch DevOps safety. Канонический порядок работ зафиксирован в `docs/45-roadmap-and-extension-points.md` и `docs/FEATURES_PLAN.md`.
