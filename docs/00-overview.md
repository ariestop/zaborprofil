# 00. Обзор проекта

## Назначение

`zaborprofil` — production-ready Symfony CMS Engine, который заменяет старый WordPress сайта `zaborprofil.ru`.

Проект решает три класса проблем одновременно:

1. **Контентная платформа.** Управляемый редактором каталог страниц, лендингов, SEO-настроек, редиректов и медиа.
2. **Маркетинговый сайт.** SSR-страницы для SEO с сохранением старых WordPress URL без потери позиций в поиске.
3. **Платформа для расширения.** Архитектурный фундамент под будущие модули: каталог, заявки, заказы, кабинеты B2B/B2C, партнёрские интерфейсы, интеграции.

## Что заменяет проект

| Что было (WordPress) | Что становится (zaborprofil engine) |
|---|---|
| Тематические темы и плагины с непредсказуемым поведением | Чистая Clean Architecture + модульный монолит |
| Шорткоды, мета-поля, ACF | Доменная модель `Page`/`PageBlock` + явные DTO |
| Автоматическое обновление плагинов с риском падения | Управляемые релизы Git + Composer + миграции |
| Слабые контракты безопасности | Symfony Security, RBAC, CSRF, voters, security headers |
| Мутный SEO-слой | Контролируемые URL, sitemap, robots, redirects, JSON-LD как часть домена |

WordPress-импортёр **не строится**. Контент переносится вручную через будущую кастомную админку.

## Целевая аудитория документации

- **Новый разработчик** — должен сесть за проект и в течение часа понимать структуру и правила.
- **DevOps-инженер** — должен иметь пошаговые сценарии деплоя, бэкапа, рестарта и runbooks.
- **Архитектор** — должен видеть, где проходят границы модулей, слоёв и контекстов, и почему.
- **AI-агент (Cursor / Codex)** — должен иметь жёсткие правила, что можно и что нельзя менять.
- **Редактор/маркетолог** — пользуется тематическими файлами `CONTENT_EDITOR_GUIDE.md`, `ADMIN_GUIDE.md`, `SEO_GUIDE.md`.

## Ключевые архитектурные решения

- Symfony 8.1+ как основной фреймворк ([ADR-0001](adr/0001-symfony-as-main-framework.md)).
- PostgreSQL 18+ как основная БД ([ADR-0002](adr/0002-postgresql-as-main-database.md)).
- Clean Architecture + Modular Monolith ([ADR-0003](adr/0003-clean-architecture.md)).
- Doctrine ORM 3 / DBAL 4 / Migrations 4 ([ADR-0004](adr/0004-doctrine-orm-usage.md)).
- DTO + Symfony Validator вместо тяжёлых FormType по умолчанию ([ADR-0005](adr/0005-dto-validator-over-heavy-formtype.md)).
- Разделение зон Front / Admin / API / Dev ([ADR-0006](adr/0006-separate-front-admin-api-dev-areas.md)).
- Redis 8 для cache, Doctrine для Messenger transport ([ADR-0007](adr/0007-redis-cache-and-messenger.md)).
- Docker только для local development ([ADR-0008](adr/0008-docker-for-local-development.md)).
- VPS-деплой без Docker для staging/production ([ADR-0009](adr/0009-vps-deployment-strategy.md)).
- SEO-first CMS-архитектура ([ADR-0010](adr/0010-seo-first-cms-architecture.md)).

## Минимальные версии runtime

| Компонент | Минимум |
|---|---|
| PHP | 8.5 |
| Symfony | 8.1 (`composer.json: "^8.1"`) |
| Node.js | 25.9.0 |
| npm | 11.12.1 |
| Nginx | 1.30.0 |
| PostgreSQL | 18 |
| Redis | 8 |
| Composer | актуальный 2.x |

## Текущие функциональные возможности (фактическое состояние)

- Authentication админки через Symfony Security (form login, login throttling, AdminUserChecker).
- RBAC (`ROLE_SUPER_ADMIN` / `ROLE_ADMIN` / `ROLE_EDITOR` / `ROLE_SEO` / `ROLE_MANAGER`) + `AdminPermissionVoter`.
- Healthcheck `/health`.
- Content Engine: `Page`, `PageBlock`, статусы и типы, Doctrine repositories, Admin API под `/admin/api/content/...`.
- Публичный SSR-рендер опубликованных страниц по `Page.path` с partial’ами блоков.
- SEO-инфраструктура: `RobotsController`, `SitemapController`, `Redirect` + `RedirectKernelSubscriber`.
- Settings-модуль с Twig-расширением.
- Telegram error handler как critical-канал Monolog, PII-redactor processor, request_id, user, release processor’ы.
- Vite + React + TypeScript + Tailwind для admin SPA, Twig + manifest для публичного сайта.
- CI/CD: GitHub Actions с lint, phpstan, rector, phpunit, Doctrine schema validate, lint:container, lint:twig, npm build; deploy.yml gated CI green.
- Docker Compose локально, native deploy скрипты в `tools/deploy/`.

## Целевые возможности (roadmap)

См. [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md) и [ROADMAP.md](legacy/ROADMAP.md). Кратко:

- Media Library и безопасные uploads с image processing.
- Menu-модуль и динамические меню.
- Lead-модуль (заявки) с антиспамом и rate limiting.
- Catalog → Product/Order/Customer для будущего e-commerce.
- AuditLog для критичных действий.
- Partner-кабинет.
- Полноценный SEO-аудит (`app:seo:audit`).

## Ключевые технические риски

- **Нарушение слоёв.** Без жёсткого контроля Domain быстро обрастает Symfony/Doctrine, и проект превращается в очередной legacy. Mitigation — [04-layer-rules](04-layer-rules.md), PHPStan, ревью.
- **SEO-регрессии.** Изменение URL без 301-редиректа — потеря трафика. Mitigation — [26-seo-architecture](26-seo-architecture.md) и обязательный `Redirect` при изменении `Page.path`.
- **Расхождение Docker (dev) и VPS (prod).** Версии PHP/Postgres/Redis должны совпадать; контролируется в [32-docker-architecture](32-docker-architecture.md) и [34-deployment](34-deployment.md).
- **Падение деплоя.** Mitigation — `tools/deploy/rollback.sh`, healthcheck после переключения релиза, обязательный backup перед prod-миграциями.
- **Утечка секретов.** Mitigation — `.env.local` / `.env.production` всегда вне Git, секреты только через GitHub Environments на CI.

## Что проект сознательно НЕ делает

- Не импортирует WordPress контент автоматически.
- Не использует EasyAdmin как основную админку.
- Не превращает публичный сайт в SPA — публичный сайт остаётся SSR (Twig).
- Не использует Docker для staging/production.
- Не строит EAV-систему «на все случаи».
- Не даёт бизнес-логике жить в контроллерах или Twig.
