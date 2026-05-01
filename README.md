# Zaborprofil CMS Engine

Новый Symfony CMS Engine для корпоративного сайта [zaborprofil.ru](https://zaborprofil.ru).

Проект заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

## Стек

- PHP `>=8.4`
- Symfony `8.x`
- PostgreSQL `>=18`
- Redis
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig
- Tailwind CSS
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

- Базовый Symfony 8 каркас без Docker.
- Авторизация админки через Symfony Security.
- Health-check `/health`.
- Content Engine: `Page`, `PageBlock`, enum статусов/типов, Doctrine repositories, Admin API.
- Публичный Twig renderer опубликованных страниц по `Page.path`.
- Twig renderer блоков с базовыми partials.
- Unit, Integration и Functional тесты для Content Engine.

## Быстрый старт через Docker

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

Следующий этап — расширить админку и перейти к SEO, sitemap, robots, redirects и Media Library.
