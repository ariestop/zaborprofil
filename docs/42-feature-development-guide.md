# 42. Feature development guide

Рабочий operating guide по разработке новых фич в Symfony CMS Engine для `zaborprofil.ru`.
Документ описывает, как пройти путь от идеи до production без поломки архитектуры,
SEO, админки, публичной части, API, миграций, деплоя и эксплуатации.

> Файл: `docs/42-feature-development-guide.md`. Это единственно правильное имя документа.
> Номер 29 занят документом [29-healthchecks](29-healthchecks.md), и feature development guide
> с этим номером не существует — все ссылки в проекте должны вести только сюда.

---

## 1. Purpose of this guide

### Зачем нужен документ

Этот guide стандартизирует процесс разработки фич в проекте, в котором одновременно живут:

- публичный SSR-сайт на Symfony + Twig (SEO-критичный),
- админка (Twig + постепенный Vue 3 SPA),
- API-зона,
- модульный монолит из доменных модулей (Page, SEO, Media, Menu, Form, Settings, Catalog, Order, Partner Cabinet, …),
- инфраструктура: PostgreSQL ≥ 18, Redis ≥ 8, Doctrine ORM, Symfony Messenger, Symfony Cache,
- Docker для local dev, native VPS stack (Nginx + PHP-FPM + systemd) для production.

В таком контексте даже маленькая фича может затронуть SEO, миграции БД, кэш, очереди,
конфиги и деплой одновременно. Без playbook новые фичи быстро ломают:

- архитектурные правила слоёв ([04-layer-rules](04-layer-rules.md)),
- стабильность URL и канонических ссылок ([26-seo-architecture](26-seo-architecture.md)),
- админ-разрешения ([20-security-and-access-control](20-security-and-access-control.md)),
- стратегию кэша ([23-cache-and-redis](23-cache-and-redis.md)),
- безопасность миграций ([18-migrations](18-migrations.md)),
- процесс деплоя ([34-deployment](34-deployment.md), [35-cicd](35-cicd.md)).

### Для кого

| Роль | Зачем читать |
|---|---|
| Разработчик | Стандартный процесс от идеи до merge. |
| Tech lead | Точки контроля качества, code review checklist. |
| Архитектор | Правила границ модулей и слоёв. |
| DevOps-инженер | Deploy/rollout/rollback awareness. |
| AI-агент / Cursor | Жёсткие правила: что можно/нельзя менять без явного запроса. |
| Новый участник | Onboarding — как тут принято делать фичи. |

### Почему CMS-проект особенно чувствителен

- Каждая публичная страница — потенциальный landing page → любое изменение URL может
  снести трафик и индексацию.
- Контент-редакторы работают через админку → любая регрессия в `/admin` блокирует бизнес.
- Контент кэшируется на нескольких уровнях (Symfony Cache, HTTP, fragment, Redis) →
  скрытое изменение генерации страницы может протухнуть месяцами.
- Миграции на боевой БД с реальными статьями необратимы без бэкапа.
- API-контракт постепенно становится публичным (партнёры, интеграции) → backward
  compatibility обязательна.

### Как использовать guide

**Человеку:**

1. Перед началом фичи — пройти [§6 Pre-implementation checklist](#6-pre-implementation-checklist).
2. Заполнить [§8 Feature design template](#8-feature-design-template) как mini-design note.
3. Использовать [§9 Where to put new code](#9-where-to-put-new-code) при сомнении про слой.
4. Перед merge — пройти [§28 Feature completion checklist](#28-feature-completion-checklist).
5. Перед деплоем — пройти [§29 Feature rollout checklist](#29-feature-rollout-checklist).

**AI-агенту / Cursor:**

1. Прочитать [§27 Common mistakes by AI agents](#27-common-mistakes-by-ai-agents) и
   [40-cursor-rules](40-cursor-rules.md) до первой правки.
2. Не выходить за scope, описанный в design note.
3. Не трогать unrelated файлы, vendor, generated assets.
4. Любое изменение архитектуры, БД, env, SEO, deploy — только с явного запроса
   и с обновлением соответствующих docs.

---

## 2. Related documentation

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
- [42-feature-development-guide](42-feature-development-guide.md) — этот документ.
- [43-module-development-guide](43-module-development-guide.md) — как добавить модуль.
- [44-troubleshooting](44-troubleshooting.md) — частые ошибки в dev.
- [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md) — куда расширять систему.
- [46-glossary](46-glossary.md) — термины проекта.

### Таблица: Ситуация / Что читать

| Ситуация | Что читать |
|---|---|
| Нужно понять архитектуру проекта | [02-architecture](02-architecture.md), [03-project-structure](03-project-structure.md), [04-layer-rules](04-layer-rules.md) |
| Нужно понять, куда класть новый код | [03-project-structure](03-project-structure.md), [04-layer-rules](04-layer-rules.md), [08-controller-architecture](08-controller-architecture.md), [09-application-layer](09-application-layer.md), [10-domain-layer](10-domain-layer.md), [11-infrastructure-layer](11-infrastructure-layer.md) |
| Нужно добавить новую публичную страницу | [13-front-area](13-front-area.md), [16-routing](16-routing.md), [21-templates-and-twig](21-templates-and-twig.md), [26-seo-architecture](26-seo-architecture.md) |
| Нужно добавить фичу в админку | [12-admin-area](12-admin-area.md), [20-security-and-access-control](20-security-and-access-control.md), [21-templates-and-twig](21-templates-and-twig.md), [22-frontend-assets](22-frontend-assets.md) |
| Нужно добавить API endpoint | [14-api-area](14-api-area.md), [20-security-and-access-control](20-security-and-access-control.md), [30-error-handling](30-error-handling.md) |
| Нужно изменить БД | [17-doctrine-and-database](17-doctrine-and-database.md), [18-migrations](18-migrations.md) |
| Нужно добавить форму | [19-forms-dto-validation](19-forms-dto-validation.md), [20-security-and-access-control](20-security-and-access-control.md) |
| Нужно добавить кэширование | [23-cache-and-redis](23-cache-and-redis.md) |
| Нужно добавить async job | [24-messenger-and-queues](24-messenger-and-queues.md) |
| Нужно добавить загрузку файлов | [25-files-and-uploads](25-files-and-uploads.md) |
| Нужно изменить SEO-поведение | [26-seo-architecture](26-seo-architecture.md) |
| Нужно добавить env/config | [27-config-and-env](27-config-and-env.md) |
| Нужно добавить логи/метрики | [28-logging-observability](28-logging-observability.md) |
| Нужно добавить healthcheck | [29-healthchecks](29-healthchecks.md) |
| Нужно обработать ошибки | [30-error-handling](30-error-handling.md) |
| Нужно написать тесты | [31-testing-strategy](31-testing-strategy.md) |
| Нужно изменить Docker/local dev | [32-docker-architecture](32-docker-architecture.md), [33-local-development](33-local-development.md) |
| Нужно изменить деплой | [34-deployment](34-deployment.md), [35-cicd](35-cicd.md) |
| Нужно добавить backup/restore logic | [36-backup-restore](36-backup-restore.md) |
| Нужно описать эксплуатационный сценарий | [37-runbooks](37-runbooks.md) |
| Нужно понять coding style | [38-coding-standards](38-coding-standards.md) |
| Работает AI-агент или Cursor | [39-agent-guide](39-agent-guide.md), [40-cursor-rules](40-cursor-rules.md) |
| Нужно реализовать фичу пошагово | [41-implementation-playbook](41-implementation-playbook.md), [42-feature-development-guide](42-feature-development-guide.md) |
| Нужно добавить новый модуль | [43-module-development-guide](43-module-development-guide.md) |
| Нужно расследовать проблему | [44-troubleshooting](44-troubleshooting.md) |
| Нужно понять будущие точки расширения | [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md) |
| Непонятен термин | [46-glossary](46-glossary.md) |

---

## 3. Feature development principles

Каждый принцип — это правило, которое можно проверить на code review.

| Принцип | Что значит | Почему важно | Как проверить |
|---|---|---|---|
| **Feature first, not random refactor** | Решаем конкретную бизнес-задачу, а не «по дороге улучшаем код». | Скрытый рефакторинг увеличивает scope и риск регрессий. | В PR — только файлы, относящиеся к design note. |
| **Architecture first** | До кода — понять, в каком модуле и слое живёт логика. | Невозможно «починить потом», см. [04-layer-rules](04-layer-rules.md). | В design note явно указаны module/layer. |
| **Minimal safe scope** | Делаем минимум, который доставляет ценность. | Меньше изменений → меньше регрессий и быстрее review. | PR < ~600 строк diff кроме миграций/фикстур. |
| **Explicit user flow** | Шаги пользователя описаны до кода. | Без flow — не сделать тесты и UX. | Раздел user story в design note. |
| **SEO awareness** | Любое изменение URL/мета/структуры — осознанное. | Потеря трафика необратима. | Чек-лист SEO ([§18](#18-developing-a-seo-related-feature)). |
| **Admin UX awareness** | Контент-редактор должен мочь работать без разработчика. | Иначе CMS превращается в hardcode. | Сценарий редактора пройден руками. |
| **API compatibility** | Не ломаем существующие endpoints без версии или редиректа. | Внешние интеграции/партнёры. | См. [14-api-area](14-api-area.md). |
| **Clear module ownership** | У каждой сущности один владелец-модуль. | Иначе циклы зависимостей. | См. [06-module-architecture](06-module-architecture.md). |
| **Domain/Application/Infrastructure separation** | Domain не знает про Doctrine, Redis, HTTP. | Тестируемость и переиспользуемость. | Domain не имеет `use Doctrine\…`/`use Symfony\…\HttpFoundation`. |
| **DTO-first input model** | Вход контроллера/use case — типизированный DTO. | Валидация и контракт. | См. [19-forms-dto-validation](19-forms-dto-validation.md). |
| **Validation before persistence** | Validator → Use Case → Repository. | Invariants нельзя поручать БД. | Тесты на негативные сценарии. |
| **Testability** | Use case можно вызвать без HTTP. | Unit/integration тесты дешёвые. | Есть unit-тест use case. |
| **Observability** | Важные операции пишут логи и метрики. | Без логов баги невидимы в prod. | См. [28-logging-observability](28-logging-observability.md). |
| **Config discipline** | Любая настройка — через `.env` + `.env.example`. | Воспроизводимость окружений. | Diff `.env.example` совпадает с design note. |
| **Migration discipline** | Ровно одна миграция на фичу, обратимая. | Откат на prod. | См. [18-migrations](18-migrations.md). |
| **Cache awareness** | Понимаем, что/где/как инвалидируется. | Иначе stale контент в SEO. | См. [23-cache-and-redis](23-cache-and-redis.md). |
| **Deployment awareness** | Знаем, что нужно сделать на VPS. | Иначе фича «работает у меня локально». | Раздел deploy impact в design note. |
| **Documentation completeness** | docs обновлены вместе с кодом. | Через 3 месяца никто не вспомнит. | См. [§24](#24-documentation-requirements-for-new-features). |
| **Rollback thinking** | Знаем, как откатить фичу. | Без отката нельзя релизить. | Раздел rollback в design note. |

---

## 4. Feature lifecycle

### Этапы

1. **Idea / request** — сформулирована бизнес-/технической задачей.
2. **Problem clarification** — ясно, какую проблему решаем и для кого.
3. **User flow design** — шаги пользователя/редактора/admin.
4. **Architecture fit** — фича вписана в текущую архитектуру или явно расширяет её.
5. **Affected modules analysis** — список затронутых модулей и слоёв.
6. **Data model decision** — нужны ли новые Entity/поля/JSONB/индексы.
7. **Cache decision** — кэшируем ли результат, как инвалидируем.
8. **SEO impact decision** — затрагиваются ли URL, sitemap, canonical, redirects, robots.
9. **Admin impact decision** — нужны ли admin screens, permissions, аудит.
10. **API impact decision** — добавляем/меняем endpoint, версионирование.
11. **Implementation design** — design note (см. [§8](#8-feature-design-template)).
12. **Coding** — последовательно по слоям.
13. **Tests** — unit + integration + functional, см. [31-testing-strategy](31-testing-strategy.md).
14. **Documentation** — обновление архитектурных и эксплуатационных docs.
15. **Deploy readiness** — env, конфиги, миграции, asset build.
16. **Rollout** — merge → CI → deploy на VPS, см. [34-deployment](34-deployment.md).
17. **Post-deploy validation** — smoke tests, проверка SEO/админки/API.
18. **Monitoring** — logs, метрики, ошибки.
19. **Rollback if needed** — заранее продуманный план отката.

### Сводная таблица

| # | Stage | Purpose | Output | Common mistake | Required artifact |
|---|---|---|---|---|---|
| 1 | Idea / request | Зафиксировать запрос | Issue/тикет | Безымянная задача | Issue / Linear ticket |
| 2 | Problem clarification | Понять «зачем» | Описание проблемы | «Сделать как у конкурента» | Раздел problem в design note |
| 3 | User flow design | Описать UX | Step-by-step | Только happy path | User story + edge cases |
| 4 | Architecture fit | Найти место в системе | Module/layer | Изобретение нового слоя | Ссылки на 04, 06, 09, 10, 11 |
| 5 | Affected modules analysis | Понять blast radius | Список модулей | «Только эта папка» | Раздел affected в design note |
| 6 | Data model decision | Решить про БД | Schema diff | JSONB как универсальный hammer | Migration draft |
| 7 | Cache decision | Решить про cache | Cache strategy | Молчаливый кэш в Twig | Раздел cache impact |
| 8 | SEO impact decision | Защитить трафик | SEO checklist | Сменили URL без 301 | SEO impact раздел |
| 9 | Admin impact decision | Учесть редактора | Admin screens | Hardcode без админки | Admin impact раздел |
| 10 | API impact decision | Сохранить контракт | API diff | Сломали ответ существующего endpoint | API impact раздел |
| 11 | Implementation design | Зафиксировать «как» | Design note | Сразу код без дизайна | `docs/<feature>.md` или PR-описание |
| 12 | Coding | Реализовать | Код + diff | Mixing layers | Соблюдение [04-layer-rules](04-layer-rules.md) |
| 13 | Tests | Защитить | Тесты | Только e2e | Unit + integration + functional |
| 14 | Documentation | Объяснить | Обновлённые docs | docs обновлён в следующем PR | PR трогает docs/ |
| 15 | Deploy readiness | Подготовить prod | env / migrations / scripts | Забыли `.env.example` | Diff в `.env.example`, секции в [34-deployment](34-deployment.md) |
| 16 | Rollout | Выкатить | Релиз | Деплой в пятницу вечером | CI green + maintenance окно если нужно |
| 17 | Post-deploy validation | Проверить | Smoke report | «Работает у меня» | Чек-лист post-deploy |
| 18 | Monitoring | Наблюдать | Логи/метрики | Нет каналов под фичу | Графики/алерты при необходимости |
| 19 | Rollback if needed | Снять риск | Rollback note | «Откатимся как-нибудь» | Заранее описанный план |

---

## 5. Feature classification

Тип фичи определяет затронутые слои, риски, тесты и требования к docs/rollout.

| Тип фичи | Typical layers touched | Key risks | Required tests | Required docs updates | Rollout caution |
|---|---|---|---|---|---|
| Front page feature | Front controller, Twig, SEO, Cache | URL/SEO, broken cache | Functional + SEO regression | [13](13-front-area.md), [16](16-routing.md), [26](26-seo-architecture.md) | Прогрев кэша, проверка sitemap |
| Landing page feature | Front, Page module, Media, SEO | Скорость, SEO, дубль контента | Functional, performance smoke | [13](13-front-area.md), [26](26-seo-architecture.md) | Canonical, OG tags |
| SEO feature | SEO module, Routing, Twig, Cache | Деиндексация | Snapshot тесты `<head>`, sitemap | [26](26-seo-architecture.md), [16](16-routing.md) | Поэтапный rollout, мониторинг GSC |
| Admin CRUD feature | Admin controller, Application, Domain, Persistence | Permissions, аудит | Admin functional + voter unit | [12](12-admin-area.md), [19](19-forms-dto-validation.md), [20](20-security-and-access-control.md) | Сначала ROLE_ADMIN, потом расширение |
| Admin UX feature | Admin controller, Twig/Vue assets | Регрессия редактора | Functional + frontend smoke | [12](12-admin-area.md), [22](22-frontend-assets.md) | Сборка ассетов |
| API feature | API controller, Application, Serializer | Backward compatibility | API contract tests | [14](14-api-area.md), [30](30-error-handling.md) | Версионирование, deprecate path |
| Content module feature | Domain + Application + Persistence + Admin + Front | Большой scope | Все уровни | [06](06-module-architecture.md), [43](43-module-development-guide.md) | Поэтапно, feature flag при необходимости |
| Media module feature | Media module, Storage, Front, Admin | Безопасность загрузок | Upload negative tests | [25](25-files-and-uploads.md) | Права на каталоги, MIME |
| Menu/navigation feature | Menu module, Front, Admin | SEO breadcrumbs | Functional | [13](13-front-area.md), [26](26-seo-architecture.md) | Кэш меню |
| Form/lead feature | Form module, Validator, Mailer, Persistence | Спам, утечка PII | Functional + spam tests | [19](19-forms-dto-validation.md), [25](25-files-and-uploads.md) | Anti-spam, rate-limit |
| Settings feature | Settings module, Cache | Локальная инвалидация | Unit + cache test | [27](27-config-and-env.md) | Не путать с env |
| Catalog feature | Catalog module, Front, Admin, SEO | URL’ы каталога | Functional + SEO | [05](05-domain-model.md), [26](26-seo-architecture.md) | URL миграция |
| Order/e-commerce feature | Order module, Persistence, Mailer, Messenger | Деньги, идемпотентность | Domain + integration | [05](05-domain-model.md), [24](24-messenger-and-queues.md) | Транзакции, ретраи |
| Partner cabinet feature | Partner module, Security | Изоляция данных | Voter + functional | [20](20-security-and-access-control.md) | RBAC |
| Auth/security feature | Security, Voters, Firewall | Доступы | Security tests | [20](20-security-and-access-control.md) | Сначала dry-run |
| Role/permission feature | Security, Voters | Эскалация прав | Voter unit + functional | [20](20-security-and-access-control.md) | Аудит изменений ролей |
| Cache/performance feature | Cache, Front, Application | Stale content | Cache invalidation tests | [23](23-cache-and-redis.md) | Прогрев и инвалидация |
| Search feature | Search module, Persistence, Cache | Релевантность | Functional | [17](17-doctrine-and-database.md), [23](23-cache-and-redis.md) | Индексы БД |
| Integration feature | Integration module, HttpClient, Messenger | Внешние сбои | Mock tests | [11](11-infrastructure-layer.md), [24](24-messenger-and-queues.md) | Timeouts, retries |
| Console command feature | Console area, Application | Долгие задачи на prod | Command unit + integration | [15](15-dev-area.md), [37](37-runbooks.md) | Запуск через systemd, logs |
| Messenger/async feature | Messenger handler, Application | Дубли, потери | Handler tests + idempotency | [24](24-messenger-and-queues.md) | Рестарт воркеров |
| Observability/logging feature | Monolog, каналы | Шум в логах | Smoke | [28](28-logging-observability.md) | Объём логов |
| Deploy/infrastructure feature | Deploy scripts, Nginx, systemd | Простой prod | Smoke на staging | [32](32-docker-architecture.md), [34](34-deployment.md), [35](35-cicd.md) | Maintenance window |

---

## 6. Pre-implementation checklist

Заполняется до первой строчки кода. Если на половину пунктов нет ответа — фича не готова к старту.

- [ ] Какую проблему решаем?
- [ ] Кто пользователь фичи (visitor / редактор / admin / partner / customer / API consumer)?
- [ ] Какой ожидаемый user flow (включая edge cases)?
- [ ] Затрагивается ли публичный сайт (Front)?
- [ ] Затрагивается ли админка (Admin)?
- [ ] Затрагивается ли API?
- [ ] Затрагивается ли SEO (URL, canonical, sitemap, robots, redirects, JSON-LD)?
- [ ] Нужна ли новая Entity?
- [ ] Нужна ли миграция БД?
- [ ] Нужен ли новый DTO?
- [ ] Нужна ли валидация?
- [ ] Нужен ли новый Service / UseCase?
- [ ] Нужен ли новый Repository?
- [ ] Нужен ли Redis (cache / lock / rate-limit / sessions)?
- [ ] Нужен ли Messenger (async job)?
- [ ] Нужен ли HTTP/Twig fragment cache?
- [ ] Нужны ли новые env-переменные?
- [ ] Нужен ли Nginx/config/deploy change?
- [ ] Нужны ли новые permissions / roles / voters?
- [ ] Нужны ли новые логи?
- [ ] Нужны ли метрики / алерты?
- [ ] Какие тесты нужны (unit / integration / functional / API)?
- [ ] Какие docs обновить?
- [ ] Можно ли уменьшить scope?
- [ ] Есть ли риск сломать существующий URL / sitemap / canonical / redirects?
- [ ] Нужен ли feature flag / поэтапный rollout?
- [ ] Есть ли план rollback?

---

## 7. How to design a feature in this project

### Как выделить use case

Use case = одно бизнес-действие пользователя. Один input DTO → один результат.
Примеры: `CreatePage`, `PublishPage`, `SubmitLeadForm`, `RegenerateSitemap`.

### Как определить границы модуля

- Логика принадлежит модулю, который владеет Entity.
- Если затрагиваются 2+ модуля — взаимодействие через application services и
  доменные события, не через прямые ссылки на чужие Entity.
- См. [06-module-architecture](06-module-architecture.md).

### Как понять, в какой слой

| Признак | Слой |
|---|---|
| Бизнес-инвариант сущности (например, «опубликованная страница не может быть без slug») | **Domain** ([10](10-domain-layer.md)) |
| Координация: загрузить, валидировать инвариант, сохранить, отправить событие | **Application** ([09](09-application-layer.md)) |
| Работа с БД, Redis, файлами, HTTP-клиентами, почтой | **Infrastructure** ([11](11-infrastructure-layer.md)) |
| Преобразование HTTP/CLI в DTO и DTO в Response | **UI / Controller** ([08](08-controller-architecture.md)) |
| Только отображение | **Twig templates** ([21](21-templates-and-twig.md)) |

### Когда нужны какие артефакты

| Артефакт | Когда нужен | Когда НЕ нужен |
|---|---|---|
| **DTO** | Любой вход в use case или контроллер с пользовательскими данными | Простой read-only без параметров |
| **Entity** | Появляется новая бизнес-сущность с identity | Если это просто значение/настройка |
| **Value Object** | Значение без identity, с инвариантами (Slug, Url, Email) | Если это просто строка без логики |
| **Domain Service** | Логика, не принадлежащая одной Entity | Если можно положить в саму Entity |
| **Application Service / UseCase** | Сценарий, который вызывается из контроллера/console/messenger | Просто вызов одного метода Repository |
| **Repository** | Доступ к Entity | Простые select-and-render запросы — лучше QueryService/Reader |
| **Event** | Кросс-модульная реакция, аудит, расширяемость | Внутри одного модуля можно прямым вызовом |
| **Messenger Message + Handler** | Долгая операция, ретраи, асинхронность | Быстрая операция в рамках запроса |
| **Console Command** | Регулярная или административная задача | Если нужно дёргать руками — лучше admin UI |
| **Symfony Cache** | Дорогие read-only данные с понятной инвалидацией | Данные, меняющиеся непредсказуемо |
| **Redis (вне Cache)** | Locks, rate-limit, sessions, очереди | Если хватает Symfony Cache поверх Redis |
| **Новый env** | Внешняя настройка/секрет, отличается между окружениями | Внутренняя бизнес-настройка → Settings module |
| **Новый route** | Публичный/админский/API endpoint | Внутренний вызов — без route |
| **Twig template** | Любая SSR-страница/partial | API JSON — не Twig |
| **Frontend asset** | UI, который видит пользователь | Серверный код |
| **Migration** | Любое изменение схемы БД | Изменения только в коде |
| **ADR** | Архитектурное решение, которое нельзя легко откатить | Локальное решение в рамках фичи |

---

## 8. Feature design template

Готов к копированию в `docs/_design/<feature>.md` или в описание PR.

```markdown
# Feature: <название>

## Status
draft | in-progress | merged | rolled-out | rolled-back

## Owner
@<github-handle>

## Goal
Одно предложение про бизнес-цель.

## Problem
Что сейчас не работает / чего не хватает.

## User story
Как <роль>, я хочу <действие>, чтобы <ценность>.
Edge cases:
- ...

## In-scope
- ...

## Out-of-scope
- ...

## Affected modules
- Page / SEO / Media / Menu / Form / Settings / Catalog / Order / ...

## Affected layers
- Front / Admin / API / Dev
- Application / Domain / Infrastructure / Persistence / UI

## Affected routes
- GET /...
- POST /admin/...

## Affected templates
- templates/front/...
- templates/admin/...

## Affected entities
- App\Module\X\Domain\Entity\Y

## Data model impact
- Новые таблицы / поля / индексы / constraints
- JSONB? обоснование

## Migration impact
- Имя миграции
- Up / Down план
- Совместимость со старым кодом во время деплоя

## Cache impact
- Какие пулы Symfony Cache затронуты
- Ключи / TTL / стратегия инвалидации

## SEO impact
- Изменения URL (с 301?)
- canonical / robots / sitemap / JSON-LD
- meta title / description

## Admin impact
- Новые экраны / меню / permissions
- Audit log

## API impact
- Новые / изменённые endpoints
- Backward compatibility / версионирование

## Security impact
- Roles / voters / CSRF / rate-limit
- Sensitive data handling

## Config impact
- Новые env / .env.example
- Symfony config файлы

## Deploy impact
- Миграции / asset build / worker restart
- Изменения Nginx / systemd / Redis

## Tests
- Unit / Integration / Functional / API / Security / Migration

## Docs
- Какие docs/* обновить

## Risks
- ...

## Rollback idea
- Как откатить (миграция down? feature flag? revert PR?).

## Acceptance criteria
- [ ] Поведение X работает
- [ ] Поведение Y работает
- [ ] Тесты проходят
- [ ] Docs обновлены
```

---

## 9. Where to put new code

Главный ориентир, который снимает 80% споров на code review.

### 9.1 Front Controller Layer

Местоположение: `src/Controller/Front/...`.

**Можно:**

- HTTP request handling.
- Привязка к route.
- Гидратация request DTO.
- Вызов application use case.
- Возврат `Response` / Twig render.
- Проставление SEO view-model.

**Нельзя:**

- Бизнес-логика.
- Прямой SQL / DBAL.
- Doctrine queries напрямую.
- Стратегия кэша внутри контроллера.
- Мутация состояния через несколько Entity.
- Объединение SEO + DB + Mailer в одной action.

```php
// good
final class PageController extends AbstractController
{
    public function __construct(private readonly ShowPage $showPage) {}

    #[Route('/{slug}', name: 'front_page_show', methods: ['GET'])]
    public function __invoke(string $slug): Response
    {
        $view = $this->showPage->run(new ShowPageQuery($slug));
        return $this->render('front/page/show.html.twig', ['page' => $view]);
    }
}
```

```php
// bad
public function show(string $slug, EntityManagerInterface $em): Response
{
    $page = $em->createQuery('SELECT p FROM ...')->getOneOrNullResult();
    if ($page === null) { throw new NotFoundHttpException(); }
    $page->setViewedAt(new \DateTimeImmutable());
    $em->flush();
    return $this->render('...');
}
```

### 9.2 Admin Controller Layer

`src/Controller/Admin/...`.

**Можно:**

- HTTP + DTO + use case + Twig/JSON.
- `#[IsGranted(...)]` или явная проверка voter.
- CSRF для форм.
- Flash messages.

**Нельзя:**

- Прямые SQL / Doctrine queries.
- Бизнес-логика, которая должна жить в Application/Domain.
- Решения о ролях inline («если username == admin»).
- Долгие операции (>500ms) синхронно — выносить в Messenger.

### 9.3 API Controller Layer

`src/Controller/Api/...`.

**Можно:**

- Парсинг JSON → DTO.
- Вызов application use case.
- Сериализация ответа через Symfony Serializer.
- Возврат стандартизованных ошибок ([30-error-handling](30-error-handling.md)).

**Нельзя:**

- Twig.
- HTML.
- Утечка Doctrine Entity наружу — только response DTO.
- Изменение публичного контракта без bump версии / deprecation.

### 9.4 Dev Controller Layer

`src/Controller/Dev/...`. Активно только при `APP_ENV=dev`.

**Можно:**

- Превью шаблонов, тестовые письма, debug endpoints.

**Нельзя:**

- Любой код, который может быть достижим в `prod`.
- Утилиты, изменяющие реальные данные.

### 9.5 Application Layer

`src/Module/<X>/Application/...` (UseCase, Command, Query, DTO, Application Services).

**Можно:**

- Координация Domain и Infrastructure.
- Транзакции (через UoW/EntityManager) — но саму бизнес-логику не пишем.
- Публикация доменных событий.
- Логирование операций уровня use case.

**Нельзя:**

- Использовать `Request`, `Response`, Twig.
- Иметь зависимости от другой модульной Application-логики напрямую (только через
  публичные интерфейсы).

### 9.6 Domain Layer

`src/Module/<X>/Domain/...`.

**Можно:**

- Entity, Value Object, Aggregate.
- Domain Service.
- Domain Event.
- Domain Exception.
- Repository **interface** (но не реализация).

**Нельзя:**

- `use Doctrine\...`.
- `use Symfony\...\HttpFoundation\...`.
- `use Symfony\...\Cache\...`.
- Работа с файловой системой / Redis / HTTP.
- Любая I/O.

### 9.7 Infrastructure Layer

`src/Module/<X>/Infrastructure/...`.

**Можно:**

- Doctrine implementations Repository.
- Redis adapters, Mailer adapters, HttpClient адаптеры.
- File storage.
- Реализация интерфейсов из Domain/Application.

**Нельзя:**

- Бизнес-инварианты.
- Прямой вызов из контроллеров минуя Application.

### 9.8 Persistence Layer

Doctrine mapping, миграции (`migrations/`), фикстуры.

**Можно:**

- Mapping XML/PHP attributes для Entity.
- Indexes / unique constraints.
- Doctrine Migrations.

**Нельзя:**

- Бизнес-логика в `prePersist` / `postLoad` (только техническое).
- Хранить логику инвариантов в БД-триггерах вместо Domain.

### 9.9 Twig Templates

`templates/front/...`, `templates/admin/...`, `templates/email/...`.

**Можно:**

- Отображение готовых view-models.
- Локальные filters/functions через Twig extension.
- Partial’ы и `embed`.

**Нельзя:**

- DB-запросы (`{{ entity.posts|filter(...) }}` если это запускает lazy-load на много данных).
- Бизнес-расчёты.
- Работа с Request/Session напрямую.

### 9.10 Frontend Assets

`assets/site/`, `assets/admin/`. Сборка через Vite.

**Можно:**

- Tailwind, Vue 3 (admin), TS.
- Прогрессивное улучшение публичной части.

**Нельзя:**

- Делать публичный сайт SPA — он остаётся SSR.
- Хардкод бизнес-логики в JS.

### 9.11 Console Commands

`src/Command/...` или `src/Module/<X>/Console/...`.

**Можно:**

- Cron-задачи, оперативные утилиты.
- Тонкая обёртка над application use case.

**Нельзя:**

- Бизнес-логика в самой команде — только вызов use case.
- Долгие задачи без логов и progress.

### 9.12 Messenger Workers

Handlers — внутри модулей: `src/Module/<X>/Application/Async/...`.

**Можно:**

- Async обработка тяжёлых операций.
- Retries, idempotency, dead-letter.

**Нельзя:**

- Класть в очередь Entity целиком — только ID/DTO.
- Полагаться на «handler точно один раз сработает».

### 9.13 Deploy Scripts

`deploy/`, `docker/`, systemd-юниты, Nginx-конфиги.

**Можно:**

- Изменения, документированные в [34-deployment](34-deployment.md).
- Идемпотентные шаги.

**Нельзя:**

- Менять prod-конфиги без обновления docs.
- Хардкодить секреты.

### 9.14 Tests

`tests/Unit/...`, `tests/Integration/...`, `tests/Functional/...`, `tests/Api/...`.

**Можно:**

- Тестировать use case без HTTP.
- Functional через `WebTestCase` для контроллеров.
- API contract tests.

**Нельзя:**

- Тестировать Twig напрямую вместо use case.
- Хрупкие e2e там, где достаточно integration.

### 9.15 Docs

`docs/`. Должны обновляться **в том же PR**:

- Архитектурные доки (02–11) — если меняется архитектура.
- Подсистемные (12–26) — если меняется поведение зоны.
- Эксплуатационные (27–37) — если меняется env/deploy/runbook.
- ADR — если решение нельзя дёшево откатить.

### Naming conventions

| Что | Конвенция | Пример |
|---|---|---|
| Controller | `<Area>\<Resource>Controller` | `Admin\PageController` |
| UseCase | `Verb<Resource>` | `CreatePage`, `PublishPage` |
| DTO команды | `Verb<Resource>Command` | `CreatePageCommand` |
| DTO запроса (read) | `Verb<Resource>Query` | `ShowPageQuery` |
| Repository interface | `<Resource>Repository` | `PageRepository` |
| Repository impl | `Doctrine<Resource>Repository` | `DoctrinePageRepository` |
| Domain Event | `<Resource><Event>` | `PagePublished` |
| Messenger Message | `<Verb><Resource>Message` | `OptimizeImageMessage` |
| Voter | `<Resource>Voter` | `PageVoter` |

### Recommended directory structure (модуль)

```
src/Module/Page/
├── Application/
│   ├── Command/
│   ├── Query/
│   ├── UseCase/
│   ├── Async/
│   └── DTO/
├── Domain/
│   ├── Entity/
│   ├── ValueObject/
│   ├── Event/
│   ├── Repository/        # interfaces
│   └── Exception/
├── Infrastructure/
│   ├── Doctrine/
│   ├── Cache/
│   └── Http/
└── UI/                    # опционально
    ├── Front/
    ├── Admin/
    └── Api/
```

---

## 10. Developing a new Front feature

1. Описать публичный user flow (вход → действия → выход).
2. Зафиксировать SEO-поведение: canonical, robots, title, description, OG, JSON-LD.
3. Определить route в `config/routes/front.yaml` (или атрибутом). См. [16-routing](16-routing.md).
4. Создать тонкий контроллер в `src/Controller/Front/...`.
5. Если есть пользовательский ввод — DTO + Validator ([19-forms-dto-validation](19-forms-dto-validation.md)).
6. Application use case (read = Query, write = Command).
7. Twig template с готовой view-model. Никакой бизнес-логики в шаблоне.
8. Breadcrumbs — через Menu/Page module, не вручную в template.
9. Canonical URL и meta — через единую SEO-services-точку ([26-seo-architecture](26-seo-architecture.md)).
10. Cache strategy — выбрать пул и ключи ([23-cache-and-redis](23-cache-and-redis.md)).
11. Тесты: functional (`WebTestCase`) + SEO regression (snapshot `<head>`).
12. Обновить docs: [13-front-area](13-front-area.md), [16-routing](16-routing.md), [26-seo-architecture](26-seo-architecture.md).

---

## 11. Developing a new Admin feature

1. Описать admin user flow (редактор / администратор).
2. Определить permissions: новая роль или новый voter? См. [20-security-and-access-control](20-security-and-access-control.md).
3. Решить размещение в admin меню (Settings module / отдельный пункт).
4. Если CRUD — описать модель: Entity → DTO → Form/DTO + Validator.
5. Список / фильтры / сортировки — через QueryService с пагинацией.
6. Форма: DTO-first ([19-forms-dto-validation](19-forms-dto-validation.md)). FormType — только когда нужен.
7. Flash messages для UX.
8. Audit logging для критичных действий (publish, delete, role change). См. [28-logging-observability](28-logging-observability.md).
9. Тесты: voter unit, functional admin (`WebTestCase` с залогиненным админом).
10. Обновить docs: [12-admin-area](12-admin-area.md), [19-forms-dto-validation](19-forms-dto-validation.md), [20-security-and-access-control](20-security-and-access-control.md).

---

## 12. Developing a new API feature

1. Зафиксировать API consumer (внутренний админ-SPA / партнёр / интеграция).
2. Контракт endpoint: метод, путь, headers, request schema, response schema, коды ошибок.
3. Request DTO + Validator.
4. Response DTO (никогда не отдаём Entity напрямую).
5. Auth требования: Bearer / session / OAuth — см. [20-security-and-access-control](20-security-and-access-control.md).
6. Rate-limit (Redis-based) при публичных endpoint’ах.
7. Стандартизованные ошибки — формат из [30-error-handling](30-error-handling.md).
8. Версионирование: `/api/v1/...`. При breaking change — `v2` + deprecation.
9. Тесты: API contract tests, негативные сценарии, авторизация.
10. Обновить docs: [14-api-area](14-api-area.md), [30-error-handling](30-error-handling.md).

---

## 13. Developing a new CMS module

Полный процесс — в [43-module-development-guide](43-module-development-guide.md). Здесь — короткий чек-лист.

| Шаг | Артефакт |
|---|---|
| Module purpose | 1–2 предложения, бизнес-цель |
| Boundaries | Какие сущности владеет, что НЕ владеет |
| Entities | Список Entity + ValueObject |
| Repositories | Interface в Domain, реализация в Infrastructure |
| Services / use cases | Application use cases |
| Controllers | По зонам Front/Admin/API |
| Templates | `templates/<area>/<module>/...` |
| Admin screens | CRUD + список + permissions |
| Routes | YAML или attributes, prefix модуля |
| Permissions | Roles + voters |
| Configs | `config/packages/<module>.yaml` если нужен |
| Migrations | Doctrine Migration |
| Tests | Unit (domain), integration (repo), functional (UI) |
| Docs | Обновить [06-module-architecture](06-module-architecture.md) и [43-module-development-guide](43-module-development-guide.md) |

Примеры существующих/целевых модулей: Page, SEO, Media, Menu, Form, Settings, Catalog, Order, Partner Cabinet.

---

## 14. Developing a DB-backed feature

Подробно см. [17-doctrine-and-database](17-doctrine-and-database.md), [18-migrations](18-migrations.md).

| Решение | Когда YES | Когда NO |
|---|---|---|
| Новая таблица | Новая бизнес-сущность с identity, отдельный жизненный цикл | Расширение существующей сущности |
| Новое поле | Атрибут существующей сущности | Если поле нужно только одной странице — view model |
| JSONB | Изменчивая структура, нет нужды индексировать всё | Если поля стабильны и нужны индексы |
| Денормализация | Read-performance критична | Простая выборка с join |

**Migration safety:**

- Имя миграции: `VersionYYYYMMDDHHMMSS_<short_description>`.
- Обязательно `up()` и `down()`.
- Для `prod` — никаких блокирующих DDL на больших таблицах без плана maintenance.
- Большие backfill — отдельной миграцией / Console command + Messenger.
- Сначала добавить колонку (nullable / default), потом backfill, потом NOT NULL.
- Не удалять колонку в том же релизе, где она ещё используется кодом — двухфазно.

**Indexes & constraints:**

- Добавлять индекс под реальный query.
- Unique-constraint на бизнес-уникальность (slug, sku, email).
- Foreign keys обязательны для связных таблиц.
- Default values — для совместимости при миграции.

**Сопутствующие обновления:**

- Entity mapping.
- Repository (поиск по новому полю).
- Fixtures / sample data.
- Тесты на новые поля и инварианты.
- Docs: [05-domain-model](05-domain-model.md), [17-doctrine-and-database](17-doctrine-and-database.md).

**Rollback thinking:**

- Можно ли откатить миграцию без потери данных?
- Если нет — должен быть бэкап ([36-backup-restore](36-backup-restore.md)) и feature flag.

---

## 15. Developing a config-driven feature

Подробно см. [27-config-and-env](27-config-and-env.md).

**Когда добавлять env:**

- Значение различается между окружениями (dev/staging/prod).
- Это секрет (API key, DSN, SMTP password).
- Внешний адрес/URL.

**Когда НЕ добавлять env:**

- Бизнес-настройка, которую меняет редактор → Settings module.
- Локальное поведение модуля → `config/packages/<module>.yaml`.
- Магическое число фичи → константа в Domain.

**Naming convention:**

- `UPPER_SNAKE_CASE`.
- Префикс по подсистеме: `MAILER_*`, `REDIS_*`, `APP_*`, `<MODULE>_*`.
- Не использовать generic-имена `URL`, `KEY`, `TOKEN`.

**Обязательно при добавлении env:**

- [ ] `.env` (значение по умолчанию для dev).
- [ ] `.env.example` (без секретов, с описанием).
- [ ] Использование через Symfony config `%env(...)` или `#[Autowire('%env(...)')]`.
- [ ] Валидация формата (тип, default).
- [ ] Описание в [27-config-and-env](27-config-and-env.md).
- [ ] Обновление [34-deployment](34-deployment.md) — где задаётся в prod.
- [ ] Обновление Docker compose / `.env.local` примеров если нужно ([32-docker-architecture](32-docker-architecture.md)).
- [ ] Failure behavior: если env отсутствует — приложение должно падать с понятной ошибкой,
      а не молча работать «как-нибудь».

---

## 16. Developing a cache/performance feature

Подробно см. [23-cache-and-redis](23-cache-and-redis.md).

**Когда кэшировать:**

- Дорогие read-only операции (агрегация контента, расчёт меню, сборка SEO-данных).
- Данные, у которых есть понятный момент инвалидации.
- Тяжёлые внешние API.

**Когда НЕ кэшировать:**

- Операции записи.
- Данные, специфичные для пользователя, без аккуратной namespace’овой стратегии.
- Если непонятно, как инвалидировать.

**Уровни:**

- `cache.app` (Redis) — application data.
- HTTP cache — для статичных публичных страниц с осторожной инвалидацией.
- Twig fragment cache — для тяжёлых partial’ов.
- Doctrine query/result cache — точечно, под конкретный запрос.

**Cache key naming:** `<module>.<entity>.<id>.<variant>`, например `page.show.slug-about.v1`.

**Invalidation strategy:**

- По доменному событию (`PageUpdated → invalidate page.show.<slug>`).
- TTL как safety net, не основная стратегия.
- Stampede protection — `lock` + `early expiration`.

**SEO risks:**

- Долгий TTL может «зацементировать» удалённую страницу → важна явная инвалидация.

**Tests & monitoring:**

- Тесты на ключи и инвалидацию.
- Метрики hit/miss, eviction.

---

## 17. Developing a Messenger/async feature

Подробно см. [24-messenger-and-queues](24-messenger-and-queues.md).

**Когда async:**

- Операция > ~500ms.
- Работа с внешними API (сеть, ретраи).
- Bulk operations.
- Email-отправка, генерация thumbnails.

**Когда НЕ async:**

- Если результат нужен синхронно в HTTP-ответе.
- Простой быстрый CRUD.

**Дизайн message:**

- В payload — только примитивы и ID, не Entity.
- Версионирование payload (поле `version`).
- Идемпотентность — handler должен корректно отрабатывать повтор.

**Handler:**

- Один message — один handler.
- Идемпотентный (повторный вызов не ломает данные).
- Не зависит от глобального состояния.

**Operational:**

- Retry policy (экспоненциальный backoff).
- Failed transport для poison messages.
- Логирование с message_id, retry_count.
- Метрики: queue length, failed count.
- При деплое — рестарт воркеров (см. [34-deployment](34-deployment.md)).

**Tests:**

- Unit handler.
- Integration с in-memory transport.
- Тесты идемпотентности.

---

## 18. Developing a SEO-related feature

SEO-критичный раздел. Любая ошибка стоит трафика.

**Чек-лист SEO impact assessment:**

- [ ] Меняются ли URL? → нужны 301 redirects.
- [ ] Сохраняется ли canonical?
- [ ] Корректный meta title (≤ 60 симв., уникальный)?
- [ ] Корректный meta description?
- [ ] robots.txt / `<meta robots>` правильные?
- [ ] sitemap.xml включает новый контент?
- [ ] structured data (JSON-LD) валиден?
- [ ] breadcrumbs корректны?
- [ ] pagination canonical/prev/next?
- [ ] нет дубликатов контента (одна и та же страница на разных URL)?
- [ ] noindex для технических страниц (search results, фильтры)?
- [ ] admin SEO fields доступны редактору?

**WordPress URL migration:**

- Карта старых URL → новых.
- 301 redirect через таблицу redirects (Redirect module / Settings).
- Проверка через `curl -I` после деплоя.
- Sitemap старого WordPress сохранён до полной индексации новых URL.

**Testing SEO output:**

- Snapshot тесты `<head>` для ключевых страниц.
- Тесты sitemap.xml (включает / не включает нужное).
- Тесты robots.txt.
- Smoke на staging: `curl -I` ключевых URL, должно быть 200/301, не 404.

**Preventing accidental deindexing:**

- Никогда не выкатывать `noindex` глобально, только точечно.
- Перед релизом — проверка `<meta robots>` через staging.
- Алерт на резкий рост 404/410.

Подробно: [26-seo-architecture](26-seo-architecture.md).

---

## 19. Developing a Media feature

Подробно см. [25-files-and-uploads](25-files-and-uploads.md).

- Upload через DTO + Validator (`File`, `Image`, max size, mime types).
- MIME проверяется через `finfo`, не только по расширению.
- Хранение: `var/uploads/` (private) и `public_html/uploads/...` (public). Никогда не выполняемый код.
- Запрет исполняемых файлов на стороне Nginx.
- Image optimization — async через Messenger.
- Thumbnails — детерминированные имена.
- Поля alt/title — обязательны для SEO.
- Cleanup при удалении сущности (event listener).
- Тесты: upload bad mime, upload too large, upload OK, удаление файлов.

---

## 20. Developing a Form/Lead feature

- Public form flow → DTO + Validator + CSRF.
- Anti-spam: honeypot + rate-limit (Redis) + опционально hCaptcha.
- Persistence: Lead Entity с created_at, IP, user-agent (с учётом 152-ФЗ).
- Email notification — async через Messenger + Mailer.
- Admin view: список + фильтр + статусы (new / in_progress / closed).
- Audit log по смене статуса.
- Privacy: маскировать PII в логах ([28-logging-observability](28-logging-observability.md)).
- Тесты: валидный лид, спам, rate-limit, CSRF, отправка письма.
- Docs: [19-forms-dto-validation](19-forms-dto-validation.md), [20-security-and-access-control](20-security-and-access-control.md).

---

## 21. Developing a security/permission feature

- Роли — только в одном месте (`security.yaml` + Roles registry).
- Voters для нетривиальных проверок (владелец ресурса, статус).
- Access control: декларативно `#[IsGranted]` или `denyAccessUnlessGranted`.
- CSRF на всех изменяющих формах.
- API auth — Bearer / session, явно описано в [20-security-and-access-control](20-security-and-access-control.md).
- Rate limiting на публичных action.
- Audit logging для смены ролей, доступа к чувствительным данным.
- Sensitive data: пароли — `password_hash`, токены — Redis с TTL.
- Secrets — только через env, не в коде, не в commit.
- Тесты: voter unit, functional (запрещённый доступ → 403), API auth.

---

## 22. Logging requirements for new features

Подробно см. [28-logging-observability](28-logging-observability.md).

**Что логировать:**

- Бизнес-события (publish page, submit lead, create order).
- Ошибки и исключения с контекстом.
- Операции безопасности (login fail, role change).
- Внешние интеграции (запрос/ответ на уровне INFO без секретов).

**Что НЕ логировать:**

- Пароли, токены, sensitive PII (телефон/email — маскировать).
- Полный body больших запросов.
- Спам в loop’ах.

**Log levels:**

- `DEBUG` — только dev.
- `INFO` — нормальный бизнес-поток.
- `NOTICE` — заметные, но не ошибки (deprecation).
- `WARNING` — деградация (retry, fallback).
- `ERROR` — обработанная ошибка с потерей операции.
- `CRITICAL` — отказ компонента.

### Таблица event type / level / context

| Event type | Level | Required context | Forbidden context | Пример |
|---|---|---|---|---|
| Use case start | DEBUG | use_case, user_id, request_id | password, token | `page.create.start` |
| Use case success | INFO | use_case, entity_id, duration_ms | full payload | `page.create.success` |
| Use case validation fail | INFO/WARNING | use_case, errors | raw user input если PII | `page.create.invalid` |
| Use case domain error | WARNING | use_case, error_code | stack trace без необходимости | `page.publish.invalid_state` |
| Internal error | ERROR | exception class, trace, request_id | секреты | `page.create.error` |
| Security event | NOTICE/WARNING | actor_id, action, target_id, ip | пароль | `auth.login.failed` |
| External call | INFO | provider, endpoint, status, duration | API key | `mailer.send.ok` |
| Async retry | WARNING | message_id, attempt, error | full payload | `messenger.retry` |
| Async permanent fail | ERROR | message_id, attempts | секреты | `messenger.dead_letter` |

Все логи — с `request_id` (для HTTP) или `message_id` (для async).

---

## 23. Testing requirements for new features

Подробно см. [31-testing-strategy](31-testing-strategy.md).

| Тип теста | Когда нужен | Что проверять | Common mistakes |
|---|---|---|---|
| **Unit** | Любая бизнес-логика в Domain | Инварианты Entity, Value Object, Domain Service | Тестировать `getter`/`setter` |
| **Application service** | Любой UseCase | Сценарий с моками портов | Использовать реальную БД |
| **Domain** | Сложные инварианты | Валидное и невалидное состояние | Тестировать через контроллер |
| **Repository (integration)** | Любой кастомный query | Реальная БД через Doctrine | Полагаться на in-memory mock |
| **Controller (functional)** | Любой контроллер | HTTP path: status, response | Подменять весь Application |
| **Functional** | Сценарий пользователя | Полный path запрос → ответ | Хрупкость через CSS-селекторы |
| **API** | Любой API endpoint | Schema response, коды ошибок | Тестировать только happy path |
| **Admin** | Любой admin action | Authorization + flow | Залогиниться неправильной ролью |
| **Security** | Voter, firewall | 401/403/200 матрица | Только happy path |
| **Migration** | Любая миграция | up() + down() обратимы | Не тестировать миграцию вообще |
| **Cache** | Кэшируемая фича | Hit/miss/invalidation | Полагаться на TTL без явной инвалидации |
| **Messenger** | Async handler | Идемпотентность, retry | Не тестировать failure path |
| **Console command** | Любая cron/admin команда | Корректное поведение, exit code | Только `--dry-run` |
| **Edge cases** | Все use cases | Пустые входы, лимиты, unicode | Только круглые числа |
| **Negative path** | Все use cases | Запрещённые состояния, ошибки валидации | Только `assertTrue` |
| **Regression** | Найденный баг | Воспроизводящий тест ДО фикса | Тест после фикса без подтверждения, что он ловит баг |

---

## 24. Documentation requirements for new features

**Какие docs обновлять:**

| Изменение | Обновить |
|---|---|
| Архитектурное | [02](02-architecture.md), [03](03-project-structure.md), [04](04-layer-rules.md) |
| Граница модуля | [06](06-module-architecture.md), [43](43-module-development-guide.md) |
| Domain model | [05](05-domain-model.md), [10](10-domain-layer.md) |
| Application layer | [09](09-application-layer.md) |
| Infrastructure | [11](11-infrastructure-layer.md) |
| Admin behavior | [12](12-admin-area.md) |
| Front behavior | [13](13-front-area.md) |
| API contract | [14](14-api-area.md) |
| Routes | [16](16-routing.md) |
| Schema БД | [17](17-doctrine-and-database.md), [18](18-migrations.md) |
| Forms / DTO | [19](19-forms-dto-validation.md) |
| Security | [20](20-security-and-access-control.md) |
| Templates | [21](21-templates-and-twig.md) |
| Frontend assets | [22](22-frontend-assets.md) |
| Cache | [23](23-cache-and-redis.md) |
| Messenger | [24](24-messenger-and-queues.md) |
| Uploads | [25](25-files-and-uploads.md) |
| SEO | [26](26-seo-architecture.md) |
| Env / config | [27](27-config-and-env.md) |
| Logging | [28](28-logging-observability.md) |
| Healthchecks | [29](29-healthchecks.md) |
| Error handling | [30](30-error-handling.md) |
| Tests | [31](31-testing-strategy.md) |
| Docker | [32](32-docker-architecture.md) |
| Local dev | [33](33-local-development.md) |
| Deploy | [34](34-deployment.md), [35](35-cicd.md) |
| Backup | [36](36-backup-restore.md) |
| Runbooks | [37](37-runbooks.md) |
| Coding standards | [38](38-coding-standards.md) |
| AI / Cursor | [39](39-agent-guide.md), [40](40-cursor-rules.md) |
| Roadmap | [45](45-roadmap-and-extension-points.md) |
| Glossary | [46](46-glossary.md) |

**Правило:** если новая фича меняет архитектуру, границы модулей, инфраструктуру,
SEO-поведение, миграции или публичный контракт API — **документация обязательна
в том же PR**. ADR — если решение нельзя дёшево откатить.

---

## 25. Deployment awareness during feature development

Каждая фича до merge должна явно ответить на эти вопросы:

| Вопрос | Когда YES |
|---|---|
| Нужны ли изменения Docker compose? | Новый сервис, новый порт, новый volume |
| Нужны ли изменения Nginx? | Новый location, новый upstream, изменение headers |
| Нужны ли новые PHP extensions? | Новая зависимость на ext-* |
| Нужны ли изменения Redis? | Новые пулы, изменение memory policy |
| Нужны ли миграции PostgreSQL и в каком порядке? | Любое изменение schema |
| Нужны ли новые secrets/env? | См. [§15](#15-developing-a-config-driven-feature) |
| Нужен ли cache warmup? | Когда холодный старт даёт деградацию |
| Нужен ли restart воркеров Messenger? | Любые изменения handler / message payload |
| Нужны ли изменения systemd / supervisor? | Новый воркер / cron |
| Нужен ли asset build (Vite)? | Изменения `assets/` |
| Нужен ли rollback plan? | Всегда |
| Нужно ли maintenance window? | Тяжёлые миграции, breaking schema, длительный backfill |

Подробно — [34-deployment](34-deployment.md), [35-cicd](35-cicd.md), [37-runbooks](37-runbooks.md).

---

## 26. Common feature development anti-patterns

| Anti-pattern | Почему опасно | Как проявляется | Как исправить |
|---|---|---|---|
| **Overbuilding** | Раздутый scope, медленный review, скрытые баги | «Заодно сделаем X, Y, Z» | Один PR — одна фича |
| **Hidden refactor** | Маскирует регрессии | В одном PR фича + переименования + переезд файлов | Разделить на 2+ PR |
| **Mixing layers** | Невозможно тестировать | DBAL в Twig, Mailer в Domain | См. [04-layer-rules](04-layer-rules.md) |
| **Fat controller** | Бизнес-логика недоступна для CLI/Async | Контроллер на 300 строк | Вынести в UseCase |
| **Fat entity** | Невозможно эволюционировать | Entity на 1000 строк, делает всё | Domain Service / Aggregate |
| **Business logic in Twig** | Хрупкость, нельзя тестировать | `{% if user.role.in([...]) %}` | View model + расчёт в Application |
| **Raw SQL in controller** | Привязка к БД, дыры в безопасности | `$em->getConnection()->query(...)` | Repository + Doctrine |
| **No migration reasoning** | Невозможно откатить | «Просто `php bin/console doctrine:schema:update --force`» | Doctrine Migration с `up`/`down` |
| **No rollback thinking** | Прод-инцидент = катастрофа | «Откатимся как-нибудь» | Раздел rollback в design note |
| **Missing docs** | Фича умирает через 3 месяца | Только код | docs/ в том же PR |
| **Missing tests** | Регрессии при следующей правке | «Проверил руками» | Unit + integration |
| **Changing routes without redirects** | Потеря SEO трафика | Переименовали URL | 301 redirect + smoke на старом URL |
| **Breaking SEO URLs** | Деиндексация | Удалили страницу без 410/301 | Чек-лист [§18](#18-developing-a-seo-related-feature) |
| **Implicit cache changes** | Stale контент в проде | Поменяли source без инвалидации | Явные ключи + invalidation на доменном событии |
| **Implicit queue changes** | Зависшие сообщения, потери | Поменяли payload — не рестартанули | Версионирование message + рестарт воркеров |
| **Unsafe admin permissions** | Эскалация прав | `IS_AUTHENTICATED_FULLY` вместо ROLE_ADMIN | Voter + functional test 403 |
| **Provider/integration assumptions not validated** | Ломается на prod | «У них всегда есть поле email» | Явная валидация ответа + fallback |
| **Happy-path-only feature** | Любая ошибка — 500 | Нет catch, нет негативных тестов | Тесты edge / negative |
| **No operational considerations** | Никто не увидит, что сломалось | Без логов и метрик | См. [§22](#22-logging-requirements-for-new-features) |
| **AI-agent changed unrelated files** | Невозможно review, регрессии | PR на 50 файлов | Жёсткий scope + rollback |

---

## 27. Common mistakes by AI agents

AI-агенты в этом проекте обязаны соблюдать [40-cursor-rules](40-cursor-rules.md) и [39-agent-guide](39-agent-guide.md).

### Типичные ошибки

| Ошибка | Чем плохо | Как избежать |
|---|---|---|
| Слишком широкий scope | PR невозможно review | Делать минимум, перечисленный в design note |
| Touching unrelated files | Регрессии, шум | Список файлов фиксируется до старта |
| Изобретение архитектуры | «Service Layer» поверх существующих UseCase | Сначала прочитать [04-layer-rules](04-layer-rules.md) |
| Логика не в том слое | Domain тянет Doctrine, контроллер делает SQL | См. [§9](#9-where-to-put-new-code) |
| Забыты docs | Через 3 месяца неизвестно, что и зачем | Обновлять docs/ вместе с кодом |
| Забыты config templates | На prod падает из-за отсутствия env | `.env.example` обязателен |
| Забыты миграции | Schema на prod не совпадает | Doctrine Migration в том же PR |
| Забыты тесты | Регрессии | Минимум: unit + functional |
| Забыта инвалидация кэша | Stale контент | Явный invalidation hook |
| Забыт SEO | Потеря трафика | Чек-лист [§18](#18-developing-a-seo-related-feature) |
| Забыты admin permissions | Эскалация / поломка редакторов | Voter + functional test |
| Забыт deploy impact | «У меня работает» | Чек-лист [§25](#25-deployment-awareness-during-feature-development) |
| Неверные предположения о существующих сервисах | Дублирующие классы | Сначала Grep по проекту |
| Неполный error handling | 500 на пустые входы | Тесты edge / negative |
| Нет rollback thinking | Невозможно откатить | Явный план в design note |
| Случайно меняет публичные URL | Деиндексация | Никогда не менять URL без 301 |
| Меняет vendor / generated файлы | CI ломается | Никогда не править `vendor/`, `var/`, сгенерированные ассеты |
| Дублирует абстракции | Растёт энтропия | Искать существующее перед созданием |
| Добавляет зависимости без обоснования | Раздутие composer | Любая новая зависимость — обоснование + ADR |

### AI agent checklist (перед началом работы)

- [ ] Прочитал [39-agent-guide](39-agent-guide.md) и [40-cursor-rules](40-cursor-rules.md).
- [ ] Прочитал этот документ — раздел [§9 Where to put new code](#9-where-to-put-new-code).
- [ ] Понимаю, какой это тип фичи (см. [§5](#5-feature-classification)).
- [ ] Заполнил [§8 Feature design template](#8-feature-design-template).
- [ ] Перечислил все файлы, которые буду менять.
- [ ] Не трогаю `vendor/`, `var/`, `node_modules/`, generated assets.
- [ ] Не меняю публичные URL без 301.
- [ ] Не меняю env без обновления `.env.example` и [27-config-and-env](27-config-and-env.md).
- [ ] Не добавляю зависимости без явного обоснования.
- [ ] Не делаю «попутных» рефакторингов.
- [ ] Каждое архитектурное решение проверено по [04-layer-rules](04-layer-rules.md).
- [ ] Все изменения покрыты тестами и docs в том же PR.

---

## 28. Feature completion checklist

Чек-лист перед merge.

- [ ] Цель фичи достигнута.
- [ ] Scope соблюдён (нет «попутных» правок).
- [ ] User flow работает на dev и staging.
- [ ] Архитектурные правила соблюдены ([04-layer-rules](04-layer-rules.md)).
- [ ] Каждый класс лежит в правильном слое.
- [ ] Нет unrelated файлов в diff.
- [ ] Все входы — через DTO + Validator.
- [ ] DB-импакт продуман (поля, индексы, constraints).
- [ ] Миграция обратима, имя по конвенции.
- [ ] Cache impact обработан (ключи, инвалидация, TTL).
- [ ] SEO impact обработан (URL, canonical, sitemap, robots, redirects).
- [ ] Admin impact обработан (permissions, screens, audit).
- [ ] API impact обработан (контракт, версии, ошибки).
- [ ] Security impact обработан (roles, voters, CSRF, rate-limit).
- [ ] Config impact обработан (`.env`, `.env.example`).
- [ ] Логи добавлены там, где нужны.
- [ ] Тесты добавлены и проходят (unit / integration / functional / api).
- [ ] Документация обновлена в том же PR.
- [ ] Deploy impact зафиксирован (миграции, asset build, рестарт воркеров).
- [ ] Есть план rollback.
- [ ] Sensitive data не попадают в логи / ответы.
- [ ] Нет performance regression на ключевых страницах.
- [ ] Нет битых публичных URL.
- [ ] Нет «забытых» error paths.

---

## 29. Feature rollout checklist

### Pre-merge

- [ ] PR small enough for review (`< ~600 строк` без миграций/фикстур).
- [ ] Self-review пройден.
- [ ] Все обязательные docs в diff.
- [ ] Линтеры / `php-cs-fixer` / Rector — green.
- [ ] PHPStan — без новых ошибок.
- [ ] Все тесты — green (`vendor/bin/phpunit`).
- [ ] `composer validate --strict` — green.
- [ ] `npm run build` — green (если фронт затронут).

### Code review

- [ ] Логика в правильном слое.
- [ ] Нет «попутных» правок.
- [ ] Тесты покрывают edge / negative.
- [ ] SEO / Admin / API impact явно проверен.

### CI gates

- [ ] Тесты, статанализ, форматтер.
- [ ] Build admin SPA.
- [ ] Migration dry-run если поддержано.

### Pre-deploy

- [ ] Миграции прогнаны на staging.
- [ ] `.env` на сервере содержит новые переменные.
- [ ] Backup БД сделан ([36-backup-restore](36-backup-restore.md)).
- [ ] Maintenance window согласовано (если нужен).

### Deploy steps

- [ ] Pull релиза.
- [ ] `composer install --no-dev --optimize-autoloader`.
- [ ] `npm ci && npm run build` (если затронут фронт).
- [ ] `php bin/console doctrine:migrations:migrate --no-interaction`.
- [ ] `php bin/console cache:clear` + warmup.
- [ ] Restart Messenger workers (`systemctl restart messenger@*`).
- [ ] Reload PHP-FPM / Nginx если конфиги менялись.

### Post-deploy

- [ ] Smoke tests основных URL (200 / 301 / правильный canonical).
- [ ] Sitemap.xml содержит / не содержит ожидаемое.
- [ ] robots.txt корректный.
- [ ] `/admin` логин работает, экран фичи открывается.
- [ ] API endpoint отвечает ожидаемо.
- [ ] Логи без ERROR / CRITICAL по новой фиче.
- [ ] Очереди Messenger обрабатываются.

### Rollback triggers

- [ ] Резкий рост 5xx.
- [ ] Резкий рост 404 на ранее живых URL.
- [ ] Падение healthcheck ([29-healthchecks](29-healthchecks.md)).
- [ ] Деградация admin или критичных API.
- [ ] Очереди не двигаются.

Откат: revert PR / restore БД / восстановить старые env. План — заранее в design note.

---

## 30. Examples of feature implementation

### Example 1: Adding `/about` public page

**Impacted layers:** Front, Page module, SEO, Twig.

**Expected files:**

- `src/Controller/Front/AboutController.php`
- `templates/front/page/about.html.twig`
- (если контент в БД) `src/Module/Page/Application/Query/ShowPageQuery.php` уже существует — переиспользуем
- роут: атрибутом или `config/routes/front.yaml`

**Tests:**

- Functional: GET `/about` → 200, тайтл присутствует, canonical совпадает с `/about`.
- SEO snapshot `<head>`.

**Docs:** [13-front-area](13-front-area.md), [16-routing](16-routing.md), [26-seo-architecture](26-seo-architecture.md).

**Risks:** конфликт с динамическим slug-роутом — `/about` должен иметь приоритет выше slug-fallback.

### Example 2: Adding SEO fields to CMS page

**Entity impact:** Page +`metaTitle`, `metaDescription`, `ogImage`, `noindex`.

**Migration impact:** ALTER TABLE pages ADD COLUMN metaTitle TEXT, ... — все nullable, default null.

**Admin impact:** новая SEO-вкладка в форме редактирования страницы; permissions те же.

**Front impact:** Twig читает из view-model, fallback на `title`/`excerpt`.

**Twig impact:** `templates/_seo/head.html.twig` использует новые поля.

**Tests:** snapshot `<head>` для страницы с заполненными и пустыми SEO полями.

**Docs:** [26-seo-architecture](26-seo-architecture.md), [12-admin-area](12-admin-area.md), [05-domain-model](05-domain-model.md).

**SEO risks:** случайно проставленный `noindex` на массе страниц — добавить admin-предупреждение и тест.

### Example 3: Adding image gallery to landing page

**Media module impact:** `Gallery` Entity, `GalleryImage` value/entity, связь с Media.

**Page module impact:** Page получает опциональную ссылку на Gallery.

**Admin impact:** экран управления галереей, выбор изображений из Media library, alt/title.

**Front impact:** новый Twig partial `_gallery.html.twig`, прогрессивное JS-улучшение (lightbox).

**Storage impact:** thumbnails — детерминированные, async через Messenger.

**Cache impact:** инвалидация кэша страницы при обновлении галереи.

**Tests:** functional админский CRUD галереи; functional Front для страницы с галереей; unit на инвалидацию кэша.

**Docs:** [25-files-and-uploads](25-files-and-uploads.md), [13-front-area](13-front-area.md), [12-admin-area](12-admin-area.md).

**Risks:** тяжёлые изображения — обязательная оптимизация, lazy loading.

### Example 4: Adding lead form

**Form DTO:** `SubmitLeadCommand { name, phone, email, message, consent }`.

**Validation:** обязательность полей, формат телефона/email, длина message, согласие.

**CSRF:** ON (публичная форма с session или stateless с одноразовым токеном).

**Rate-limit:** Redis-based, например 5/час на IP.

**Persistence:** `Lead` Entity, статусы.

**Email notification:** async через Messenger + Mailer.

**Admin view:** список / фильтр / смена статуса / экспорт.

**Tests:** валидный submit; невалидный (отсутствие consent); rate-limit; CSRF; письмо отправлено.

**Docs:** [19-forms-dto-validation](19-forms-dto-validation.md), [20-security-and-access-control](20-security-and-access-control.md), [24-messenger-and-queues](24-messenger-and-queues.md).

**Security risks:** PII в логах — маскировать; XSS в admin — экранировать; spam — honeypot + rate-limit.

### Example 5: Adding catalog category feature

**Entity:** `Category { id, slug, title, parent, sortOrder, seo... }`.

**Repository:** поиск по slug, дерево.

**Admin CRUD:** список + дерево + форма + sortable.

**Front listing:** `/catalog/{slug}` + breadcrumbs + pagination.

**SEO:** canonical, JSON-LD `BreadcrumbList`, sitemap включает категории.

**Migration:** создание таблицы, индексы по slug (unique) и parent.

**Tests:** репозиторий, admin CRUD, front listing, SEO snapshot.

**Docs:** [05-domain-model](05-domain-model.md), [26-seo-architecture](26-seo-architecture.md), [13-front-area](13-front-area.md).

**Rollout:** при миграции с WordPress — карта URL и 301.

### Example 6: Adding env-controlled behavior

Сценарий: feature flag `APP_FEATURE_PARTNER_CABINET=true|false`.

- Naming: `APP_FEATURE_<NAME>`.
- Config: `parameters: app.feature.partner_cabinet: '%env(bool:APP_FEATURE_PARTNER_CABINET)%'`.
- `.env` — `APP_FEATURE_PARTNER_CABINET=false`.
- `.env.example` — то же значение + комментарий.
- Deploy docs ([34-deployment](34-deployment.md)) — раздел про новую переменную.
- Тесты: фича выключена → 404 на роутах кабинета; включена → доступ согласно ролям.
- Risks: рассинхронизация значения между worker’ами и web — гарантировать общий source-of-truth env.

### Example 7: Adding async image optimization via Messenger

**Message:** `OptimizeImageMessage { imageId: int, version: 1 }`.

**Handler:** загрузить файл → оптимизировать → сохранить производные → пометить Image как processed.

**Queue:** `messenger.transport.async` (Doctrine transport), или отдельный transport `images`.

**Retry:** exponential backoff, max 5 попыток.

**Idempotency:** при повторной обработке — не плодить thumbnails, использовать детерминированные имена.

**Worker restart:** обязателен после деплоя при изменении payload.

**Logs:** `image.optimize.start/success/error` + `message_id`.

**Tests:** unit handler (success / fail / idempotent); integration с in-memory transport.

**Deploy risks:** очередь забита старыми сообщениями с другим payload → версионирование `version` поля и graceful fallback.

---

## 31. Quick reference

Daily-use шпаргалка. 15 правил.

1. Сначала design note ([§8](#8-feature-design-template)) — потом код.
2. Никогда не класть бизнес-логику в контроллер.
3. Domain не знает про Doctrine, Symfony HttpFoundation, Twig, Redis.
4. Любой вход — через DTO + Validator.
5. Любое изменение БД — через Doctrine Migration с `up()` и `down()`.
6. Любое изменение env — обновить `.env.example` и [27-config-and-env](27-config-and-env.md).
7. Любое изменение URL — 301 redirect и проверка sitemap.
8. Любое изменение кэшируемых данных — продумать invalidation.
9. Любая async-операция — идемпотентна, с retry и логами.
10. Любое изменение admin — проверить permissions через voter.
11. Любая фича = тесты (unit + функциональный) + docs в одном PR.
12. Не менять unrelated файлы. Не делать «попутных» рефакторингов.
13. Не добавлять зависимости без обоснования.
14. Перед merge — пройти [§28 completion checklist](#28-feature-completion-checklist).
15. Перед deploy — пройти [§29 rollout checklist](#29-feature-rollout-checklist) и иметь план rollback.

---

## 32. Что читать дальше

- Если задача про **архитектуру** — [02-architecture](02-architecture.md), [03-project-structure](03-project-structure.md), [04-layer-rules](04-layer-rules.md).
- Если задача про **новый модуль** — [43-module-development-guide](43-module-development-guide.md).
- Если задача про **пошаговую реализацию** — [41-implementation-playbook](41-implementation-playbook.md).
- Если работает **AI-агент / Cursor** — [39-agent-guide](39-agent-guide.md), [40-cursor-rules](40-cursor-rules.md).
- Если задача про **тесты** — [31-testing-strategy](31-testing-strategy.md).
- Если задача про **деплой** — [34-deployment](34-deployment.md), [35-cicd](35-cicd.md).
- Если задача про **SEO** — [26-seo-architecture](26-seo-architecture.md).
- Если задача про **БД** — [17-doctrine-and-database](17-doctrine-and-database.md), [18-migrations](18-migrations.md).
- Если задача про **troubleshooting** — [44-troubleshooting](44-troubleshooting.md).
- Если **непонятен термин** — [46-glossary](46-glossary.md).
