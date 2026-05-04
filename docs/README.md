# Документация проекта `zaborprofil`

Это единый канонический пакет документации Symfony CMS Engine для `zaborprofil.ru`.

Документация рассчитана и на людей (новые разработчики, DevOps, архитекторы), и на AI-агентов (Cursor, Codex). Перед любыми изменениями кода обязательно ознакомьтесь с разделами [04-layer-rules](04-layer-rules.md) и [40-cursor-rules](40-cursor-rules.md).

> Конвенция статусов:
>
> - **Фактическое состояние** — то, что уже реализовано в коде.
> - **Целевое состояние** — рекомендация, которая ещё не реализована.
> - **Запрещено** — нарушение архитектурных границ или правил безопасности.

## Обязательная последовательность чтения

Минимум для безопасного внесения изменений:

1. [00-overview](00-overview.md)
2. [02-architecture](02-architecture.md)
3. [03-project-structure](03-project-structure.md)
4. [04-layer-rules](04-layer-rules.md)
5. [38-coding-standards](38-coding-standards.md)
6. [39-agent-guide](39-agent-guide.md) и [40-cursor-rules](40-cursor-rules.md), если изменения вносит AI-агент.

Для запуска проекта на Windows используйте Docker Desktop + WSL2 и выполняйте команды из Ubuntu/WSL. Пошаговая инструкция находится в [33-local-development](33-local-development.md) и [LOCAL_DOCKER](LOCAL_DOCKER.md).

Дополнительные обязательные чтения по контексту задачи:

| Что меняется | Обязательные документы |
|---|---|
| Доменная модель / Entity | [05-domain-model](05-domain-model.md), [10-domain-layer](10-domain-layer.md), [17-doctrine-and-database](17-doctrine-and-database.md), [18-migrations](18-migrations.md) |
| Application / use case | [09-application-layer](09-application-layer.md), [19-forms-dto-validation](19-forms-dto-validation.md), [30-error-handling](30-error-handling.md) |
| Контроллеры / роуты | [08-controller-architecture](08-controller-architecture.md), [16-routing](16-routing.md), [12-admin-area](12-admin-area.md), [13-front-area](13-front-area.md), [14-api-area](14-api-area.md) |
| Шаблоны / SSR | [21-templates-and-twig](21-templates-and-twig.md), [22-frontend-assets](22-frontend-assets.md), [26-seo-architecture](26-seo-architecture.md) |
| Безопасность | [20-security-and-access-control](20-security-and-access-control.md), [25-files-and-uploads](25-files-and-uploads.md) |
| Кеш / Redis | [23-cache-and-redis](23-cache-and-redis.md) |
| Очереди / Messenger | [24-messenger-and-queues](24-messenger-and-queues.md) |
| Деплой / инфраструктура | [32-docker-architecture](32-docker-architecture.md), [33-local-development](33-local-development.md), [34-deployment](34-deployment.md), [35-cicd](35-cicd.md), [36-backup-restore](36-backup-restore.md), [37-runbooks](37-runbooks.md), [47-dev-database-state](47-dev-database-state.md) |
| Новый модуль | [06-module-architecture](06-module-architecture.md), [43-module-development-guide](43-module-development-guide.md) |
| Новая фича end-to-end | [41-implementation-playbook](41-implementation-playbook.md), [42-feature-development-guide](42-feature-development-guide.md) |

## Полный индекс

### Обзор и архитектура

- [00-overview](00-overview.md) — что это за проект и почему он существует.
- [01-product-purpose](01-product-purpose.md) — продуктовые цели и user journeys.
- [02-architecture](02-architecture.md) — высокоуровневая архитектура, диаграммы, lifecycle.
- [03-project-structure](03-project-structure.md) — что где лежит в репозитории.
- [04-layer-rules](04-layer-rules.md) — правила зависимостей между слоями, allowed / forbidden.
- [05-domain-model](05-domain-model.md) — текущая и целевая доменная модель CMS.
- [06-module-architecture](06-module-architecture.md) — модульный монолит, границы модулей.
- [07-request-flow](07-request-flow.md) — путь HTTP-запроса, console, messenger.

### Слои и зоны

- [08-controller-architecture](08-controller-architecture.md) — Front / Admin / API / Dev контроллеры.
- [09-application-layer](09-application-layer.md) — use cases, DTO, command/query.
- [10-domain-layer](10-domain-layer.md) — Entity, Value Object, Domain Service.
- [11-infrastructure-layer](11-infrastructure-layer.md) — Doctrine, Redis, Mailer, Storage.
- [12-admin-area](12-admin-area.md) — `/admin` зона.
- [13-front-area](13-front-area.md) — публичный сайт.
- [14-api-area](14-api-area.md) — публичные и admin API.
- [15-dev-area](15-dev-area.md) — dev-инструменты, profiler.
- [16-routing](16-routing.md) — конвенции роутов и приоритетов.

### Подсистемы

- [17-doctrine-and-database](17-doctrine-and-database.md) — PostgreSQL, ORM, naming, индексы.
- [18-migrations](18-migrations.md) — Doctrine Migrations, правила и чек-листы.
- [19-forms-dto-validation](19-forms-dto-validation.md) — DTO + Validator, когда FormType.
- [20-security-and-access-control](20-security-and-access-control.md) — Symfony Security, RBAC, voters, CSRF.
- [21-templates-and-twig](21-templates-and-twig.md) — Twig структура, partial’ы, view models.
- [22-frontend-assets](22-frontend-assets.md) — Vite, Vue 3, Tailwind, admin SPA.
- [23-cache-and-redis](23-cache-and-redis.md) — пулы Symfony Cache, инвалидация.
- [24-messenger-and-queues](24-messenger-and-queues.md) — Doctrine transport, worker.
- [25-files-and-uploads](25-files-and-uploads.md) — uploads, безопасность, хранение.
- [26-seo-architecture](26-seo-architecture.md) — URL, sitemap, robots, redirects, JSON-LD.

### Эксплуатация и качество

- [27-config-and-env](27-config-and-env.md) — `.env`, секреты, окружения.
- [28-logging-observability](28-logging-observability.md) — Monolog, каналы, request_id.
- [29-healthchecks](29-healthchecks.md) — `/health`, runtime checks.
- [30-error-handling](30-error-handling.md) — иерархия исключений и форматы ошибок.
- [31-testing-strategy](31-testing-strategy.md) — пирамида тестов, naming, fixtures.

### DevOps

- [32-docker-architecture](32-docker-architecture.md) — состав compose, сценарии.
- [33-local-development](33-local-development.md) — пошаговый local guide.
- [34-deployment](34-deployment.md) — релизы на VPS без Docker.
- [35-cicd](35-cicd.md) — GitHub Actions, gates.
- [36-backup-restore](36-backup-restore.md) — бэкапы PostgreSQL и uploads.
- [37-runbooks](37-runbooks.md) — что делать при инцидентах.

### Стандарты и гайды

- [38-coding-standards](38-coding-standards.md) — стиль кода, PHPStan, Rector.
- [39-agent-guide](39-agent-guide.md) — как AI-агент должен работать с проектом.
- [40-cursor-rules](40-cursor-rules.md) — строгие правила для Cursor.
- [41-implementation-playbook](41-implementation-playbook.md) — пошаговые рецепты.
- [42-feature-development-guide](42-feature-development-guide.md) — фича от идеи до prod.
- [43-module-development-guide](43-module-development-guide.md) — как добавить модуль.
- [44-troubleshooting](44-troubleshooting.md) — частые ошибки в dev.
- [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md) — куда расширять систему.
- [46-glossary](46-glossary.md) — термины проекта.
- [47-dev-database-state](47-dev-database-state.md) — единое dev-состояние БД, fixtures и правила для dev snapshots в Git.

### ADR

- [adr/0001-symfony-as-main-framework](adr/0001-symfony-as-main-framework.md)
- [adr/0002-postgresql-as-main-database](adr/0002-postgresql-as-main-database.md)
- [adr/0003-clean-architecture](adr/0003-clean-architecture.md)
- [adr/0004-doctrine-orm-usage](adr/0004-doctrine-orm-usage.md)
- [adr/0005-dto-validator-over-heavy-formtype](adr/0005-dto-validator-over-heavy-formtype.md)
- [adr/0006-separate-front-admin-api-dev-areas](adr/0006-separate-front-admin-api-dev-areas.md)
- [adr/0007-redis-cache-and-messenger](adr/0007-redis-cache-and-messenger.md)
- [adr/0008-docker-for-local-development](adr/0008-docker-for-local-development.md)
- [adr/0009-vps-deployment-strategy](adr/0009-vps-deployment-strategy.md)
- [adr/0010-seo-first-cms-architecture](adr/0010-seo-first-cms-architecture.md)
- [adr/0011-ulid-identifiers](adr/0011-ulid-identifiers.md)
- [adr/0012-admin-shell-spa-pattern](adr/0012-admin-shell-spa-pattern.md)

### Архивные справочники (legacy)

Все исторические тематические документы перенесены в [legacy/](legacy/README.md). Они оставлены **только** для совместимости с внешними ссылками. Канонический источник — нумерованные `NN-*.md` файлы. См. карту соответствий: [legacy/README.md](legacy/README.md).
