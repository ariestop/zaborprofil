# Roadmap

> Актуальный источник истины для продолжения разработки — `docs/45-roadmap-and-extension-points.md`
> и `docs/FEATURES_PLAN.md`. Этот файл оставлен как краткая историческая
> сводка этапов и не должен противоречить фактическому baseline.

## Этап 1

- Symfony 8 skeleton.
- PHP 8.5 constraint.
- PostgreSQL, Redis, Doctrine, Security.
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector.
- Vite, Tailwind CSS, Vue 3 entrypoint.
- `src/Shared` и `src/Module`.
- `/health` и `/admin/login`.
- GitHub Actions CI.
- Базовая документация.

## Этап 2

Готово: есть `User`, `Auth`, `Admin`, RBAC, `AdminPermissionVoter`, UserChecker для неактивных администраторов и audit/security hooks для ключевых admin/API-сценариев.

Дальше:

- seed-команда администратора;
- 2FA TOTP перед публичным запуском.

## Этап 3

Готово: реализованы `Content`, `Page`, `PageBlock`, enum, Doctrine repositories, application handlers и Admin API.

## Этап 4

Готово: опубликованные страницы открываются по `Page.path`, блоки рендерятся через Twig partials, preview links показывают черновики с `noindex,nofollow`, публичный layout выводит меню, breadcrumbs и lead-форму.

Дальше:

- расширить набор Twig partials для всех типов блоков;
- добавить полноценный Vue block editor.

## Этапы 5-13

Фактический baseline уже включает SEO metadata, redirects, sitemap index/chunks, robots manager, settings, audit log, Vue admin shell, Media Library, Menu, Leads, cache invalidation, preview links, DevOps safety, Dev/QA readiness и базовый Catalog / Product / Variant с публичным SSR и sitemap/SEO.

Commerce, Customer, Public API, reverse proxy cache и domain events вынесены в более позднюю реализацию.

## Инфраструктура и deploy

Готово:

- Docker Compose для local development: PHP-FPM 8.5, Nginx 1.30.0, PostgreSQL 18, Redis 8, Node.js 25.9.0, Mailpit, Adminer.
- `Makefile` для локальных команд разработки и quality pipeline.
- Health-check `/health` с проверкой приложения, database, Redis/cache и storage.
- GitHub Actions CI для backend/frontend проверок.
- GitHub Actions deploy workflow: staging first, production только по `v*` tag после staging и GitHub Environment approval.
- Native VPS deploy scripts без Docker: staging, production, rollback, health-check.
- Templates для Nginx и systemd Messenger workers.
- Документация по local Docker, staging, production, deploy, CI/CD и restore backup.
- `make init`, расширенный `make quality` и `app:smoke:test` для release readiness.

Дальше:

- проверить Docker runtime на Windows через Docker Desktop + WSL2;
- выполнить первый smoke deploy на staging VPS;
- настроить GitHub Environments и secrets для staging/production;
- установить Nginx и systemd templates на VPS;
- настроить staging protection: basic auth или IP allowlist;
- проверить production deploy на тестовом `v*` tag после успешного staging;
- провести restore rehearsal: database backup restore и uploads backup restore;
- поддерживать PostgreSQL-backed тесты в CI и локальном Docker без SQLite fallback;
- документировать реальные VPS значения `PHP_FPM_SERVICE`, `WORKER_SERVICE`, paths и backup retention;
- добавить monitoring/log rotation/alerting для PHP-FPM, Nginx, Messenger, PostgreSQL и Redis.
