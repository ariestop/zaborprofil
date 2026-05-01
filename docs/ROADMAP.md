# Roadmap

## Этап 1

- Symfony 8 skeleton.
- PHP 8.4 constraint.
- PostgreSQL, Redis, Doctrine, Security.
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector.
- Vite, Tailwind CSS, Vue 3 entrypoint.
- `src/Shared` и `src/Module`.
- `/health` и `/admin/login`.
- GitHub Actions CI.
- Базовая документация.

## Этап 2

Частично готово: есть базовые `User`, `Auth`, `Admin`.

Дальше:

- seed-команда администратора;
- UserChecker для неактивных администраторов;
- расширенная матрица ролей и прав;
- audit hooks для действий в админке.

## Этап 3

Готово: реализованы `Content`, `Page`, `PageBlock`, enum, Doctrine repositories, application handlers и Admin API.

## Этап 4

Готово базово: опубликованные страницы открываются по `Page.path`, блоки рендерятся через Twig partials.

Дальше:

- расширить набор Twig partials для всех типов блоков;
- добавить preview mode;
- добавить полноценный Vue block editor.

## Этапы 5-13

Следующие приоритеты: SEO metadata, sitemap, robots, redirects, media, lead forms, portfolio, settings, menu, audit log, Vue admin UI и deploy-документация.

## Инфраструктура и deploy

Готово:

- Docker Compose для local development: PHP-FPM 8.4, Nginx, PostgreSQL 18, Redis, Node.js, Mailpit, Adminer.
- `Makefile` для локальных команд разработки и quality pipeline.
- Health-check `/health` с проверкой приложения, database, Redis/cache и storage.
- GitHub Actions CI для backend/frontend проверок.
- GitHub Actions deploy workflow: staging first, production только по `v*` tag после staging и GitHub Environment approval.
- Native VPS deploy scripts без Docker: staging, production, rollback, health-check.
- Templates для Nginx и systemd Messenger workers.
- Документация по local Docker, staging, production, deploy, CI/CD и restore backup.

Дальше:

- проверить Docker runtime на Windows через Docker Desktop + WSL2;
- выполнить первый smoke deploy на staging VPS;
- настроить GitHub Environments и secrets для staging/production;
- установить Nginx и systemd templates на VPS;
- настроить staging protection: basic auth или IP allowlist;
- проверить production deploy на тестовом `v*` tag после успешного staging;
- провести restore rehearsal: database backup restore и uploads backup restore;
- добавить отдельный PostgreSQL-backed integration job в CI, чтобы тесты проверяли не только SQLite;
- документировать реальные VPS значения `PHP_FPM_SERVICE`, `WORKER_SERVICE`, paths и backup retention;
- добавить monitoring/log rotation/alerting для PHP-FPM, Nginx, Messenger, PostgreSQL и Redis.
