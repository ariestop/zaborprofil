# 39. Agent Guide

> Этот документ — **операционная конституция** проекта для AI-агентов (Cursor, Codex, Claude, ChatGPT)
> и для разработчиков, работающих через AI.
>
> Цель: удержать архитектуру Symfony CMS Engine `zaborprofil.ru` от деградации, защитить SEO,
> миграции, безопасность и деплой от хаотичных изменений и сделать любые правки **минимально безопасными**.

---

## 1. Назначение документа

### 1.1 Зачем нужен этот guide

AI-агенты по умолчанию ведут себя как «генератор PHP-файлов»: дописывают код в ближайшее место, переносят
бизнес-логику в контроллеры, тащат `EntityManager` в Twig, выдумывают несуществующие сервисы и переписывают
unrelated файлы «по пути». В CMS-проекте с SEO, миграциями PostgreSQL, очередями Messenger и
production-VPS это приводит к одному из четырёх сценариев:

1. молчаливая деградация архитектурных границ;
2. сломанный SEO (изменился URL/canonical/sitemap — и трафик потерян на месяцы);
3. неконтролируемые миграции БД (destructive change без транзитного плана);
4. сломанный деплой (нарушена идемпотентность install/deploy скриптов).

Документ существует, чтобы **этого не произошло**.

### 1.2 Для кого

- AI-агенты (Cursor, Codex, Claude и др.), вносящие изменения в код или документацию.
- Разработчики, делегирующие задачи AI и принимающие diff на review.
- Архитекторы и tech leads, проверяющие соответствие изменений архитектурным правилам.

### 1.3 Какую проблему решает

| Проблема                                              | Как решает документ                                                                |
|-------------------------------------------------------|------------------------------------------------------------------------------------|
| AI ломает слои архитектуры                            | Жёсткие non-negotiable rules + layer-by-layer правила (раздел 3, 8)                |
| AI делает огромные diff на маленькую задачу           | Safe change protocol + minimum safe scope (раздел 6)                                |
| AI ломает SEO случайно                                | Отдельные правила для URL, slug, sitemap, canonical (раздел 10)                     |
| AI делает destructive миграции                        | Правила миграций expand/contract + pre-flight (раздел 9)                            |
| AI забывает обновить tests/docs/migrations            | Documentation/testing obligations + Definition of Done (разделы 16, 17, 22)        |
| AI игнорирует deploy/security/cache impact            | Impact analysis + escalation rules (разделы 7, 23)                                  |

### 1.4 Почему особенно важно соблюдать правила в этом проекте

- Это **CMS Engine с SEO-first архитектурой**. Каждая публичная страница может быть посадочной.
  Любое изменение URL или canonical = риск потери поискового трафика.
- Это **модульный монолит** (Clean Architecture + DDD-light). Размытие границ модулей деградирует
  систему за 5–10 PR'ов до неподдерживаемого состояния.
- На production используется **native VPS stack** (Nginx + PHP-FPM + PostgreSQL + Redis + systemd),
  а не Docker. Изменения в deploy и Nginx не имеют «отката одной кнопкой».
- В будущем планируются интернет-магазин, B2B/B2C кабинеты, партнёрская программа, CRM-интеграции.
  Хаос в текущей архитектуре сделает их невозможными.

### 1.5 Почему нельзя относиться к проекту как к набору случайных PHP-файлов

Каждая директория, имя класса и слой — часть **контракта**. См. `docs/04-layer-rules.md`.
Нарушение контракта без обоснования = архитектурная регрессия, а не «фича».

---

## 2. Mental model проекта

### 2.1 Краткая модель

`zaborprofil` — это **Symfony 8.1+ CMS Engine**, замещающий WordPress, со следующими ключевыми свойствами:

- **SSR** на Symfony + Twig для публичного сайта (SEO-критично).
- **Vue 3 SPA** для админки (отдельная зона `/admin`).
- **Clean Architecture + Modular Monolith**: слои UI → Application → Domain ← Infrastructure.
- **Bounded modules** в `src/Module/<Name>/{Domain, Application, Infrastructure, UI}`.
- **Web root** — `public_html/`, не Symfony default `public/`.
- **PostgreSQL 18+** как основная БД, миграции через Doctrine Migrations.
- **Redis 8+** только для cache (Symfony Cache pools). Sessions — нативные файлы, Messenger — Doctrine transport. См. [ADR-0007](adr/0007-redis-cache-and-messenger.md).
- **VPS deployment** через bash + systemd, без Docker на проде.

### 2.2 Как думать о проекте

| Уровень                       | Что находится                                                  | Source of truth                                |
|-------------------------------|----------------------------------------------------------------|-----------------------------------------------|
| Контракт продукта             | `docs/01-product-purpose.md`                                   | docs                                           |
| Контракт архитектуры          | `docs/02-architecture.md`, `docs/04-layer-rules.md`            | docs + ADR                                     |
| Контракт модулей              | `docs/06-module-architecture.md`, `src/Module/*/README.md`     | docs + код                                     |
| Контракт БД                   | Doctrine entities + `migrations/` + `docs/17`, `docs/18`        | миграции (применённые)                         |
| Контракт публичных URL        | Routing + `docs/16-routing.md` + `docs/26-seo-architecture.md` | роуты + sitemap                                |
| Контракт API                  | DTO + контроллеры + `docs/14-api-area.md`                       | DTO/нормализаторы                              |
| Контракт деплоя               | `deploy/`, systemd unit'ы, `docs/34`, `docs/37`                 | bash-скрипты + runbooks                        |

### 2.3 Где живёт что

- **Бизнес-логика** — `src/Module/<X>/Domain` (правила) и `src/Module/<X>/Application` (сценарии).
  **Никогда** в контроллерах, Twig, Doctrine listeners или Messenger handlers напрямую.
- **Presentation logic** — Twig + view models + `src/.../UI/Twig`/`UI/Http/Controller`.
- **Persistence logic** — `src/Module/<X>/Infrastructure/Doctrine/*`, миграции в `migrations/`.
- **SEO logic** — `src/Module/Seo/*`, Twig partial'ы (`templates/_seo/*`), `RedirectKernelSubscriber`,
  sitemap generator. См. `docs/26-seo-architecture.md`.
- **Deploy/config concerns** — `deploy/`, `.env*`, `config/packages/*`, Nginx, systemd unit'ы.
  Никогда в коде Domain/Application.

### 2.4 Самые хрупкие места

1. **Routing + slug + redirects** — ломаются тихо и платятся SEO-трафиком.
2. **Doctrine миграции** — destructive changes на production откатываются дорого.
3. **Messenger transport** — задачи в очереди могут пережить deploy и сломаться при изменении DTO.
4. **Cache invalidation** — stale данные в Redis после миграций или deploy.
5. **Nginx конфиг** — правила `location`, `try_files`, redirects влияют на каждый запрос.
6. **Security: voters, форма логина, uploads, CSRF** — регрессии тихие и опасные.

### 2.5 Почему контроллеры тонкие, а Domain/Application чистые

- **Тонкий контроллер** = легко тестировать use case без HTTP, легко добавить API/CLI поверх той же логики.
- **Чистый Domain** = бизнес-правила переживают смену Symfony major version, смену Doctrine на иной ORM,
  замену Twig на Vue. Если Domain знает про `Request` или `EntityManager` — его нельзя переиспользовать.
- **Чистый Application** = use case'ы тестируются юнит-тестом без БД, без HTTP, без Twig.

---

## 3. Non-negotiable architecture rules

Это **не рекомендации**. Нарушение — блокер merge.

### 3.1 Слои

- Domain не зависит от Symfony, Doctrine ORM (вызовов), Redis, Twig, HTTP, FS, Mailer.
  Допустимо: Doctrine attributes для маппинга, `Symfony\Component\Uid`, PHP stdlib, `App\Shared\Domain\*`.
- Application не зависит от `Request/Response`, `EntityManagerInterface`, конкретных Doctrine repositories,
  Twig, Symfony bundles. Использует только interfaces из Domain/Application + чистые value-classes.
- Infrastructure реализует interfaces Domain/Application. Здесь живут Doctrine repositories, Mailer,
  Redis, HTTP-clients, файловое хранилище.
- UI (controllers, console, messenger handlers) — **тонкий адаптер**: парсит вход → вызывает Application → формирует ответ.

### 3.2 Контроллеры

- Не содержат бизнес-логику, не вызывают `EntityManager`, не пишут SQL.
- Только: получение DTO/валидация → вызов Application handler → формирование Response.
- Front, Admin, API, Dev контроллеры **не смешиваются** между собой по namespace и по логике.

### 3.3 Twig

- Не содержит сложную бизнес-логику, не делает Doctrine queries, не лезет в Repositories.
- Допустимо: итерация по подготовленному view model, фильтры, форматирование, простые условия.
- Сложная подготовка данных — в Application/UI helper или Twig extension с явным контрактом.

### 3.4 Doctrine entities

- Не превращаются в god objects: ровно отвечают за состояние агрегата.
- Lifecycle callbacks допустимы только для технических задач (timestamps), не для бизнес-логики.
- Не используются как DTO для API/Twig — наружу идут view models или DTO.

### 3.5 Repositories

- Domain содержит `*RepositoryInterface` с минимальным набором методов.
- Infrastructure содержит `Doctrine*Repository implements *RepositoryInterface`.
- Repositories **запрашивают данные**, не выполняют бизнес-сценарии.
- `findAll()` без пагинации — запрещён в production-коде.
- `QueryBuilder` не утекает за пределы Infrastructure.

### 3.6 Validators

- Validator проверяет инвариант, не выполняет use case, не делает побочные эффекты.
- Сложные правила («уникальность с учётом мягкого удаления», «существование slug в другом модуле») —
  отдельные `Constraint` + `ConstraintValidator` с зависимостями через DI, не inline в DTO.

### 3.7 Console commands

- Команда — тонкий адаптер CLI → Application handler.
- Не пишут бизнес-логику в `execute()`, не вызывают `EntityManager` напрямую.
- Логируют входы/выходы, возвращают корректный exit code.

### 3.8 Messenger handlers

- Handler — тонкий адаптер сообщения → Application handler.
- Не содержат бизнес-логику inline, не делают «толстые» транзакции внутри handler.
- Идемпотентны, либо явно документируют отсутствие идемпотентности.

### 3.9 Migrations

- Любое изменение схемы = новая миграция. Не редактируем уже применённые.
- Миграции работают с DBAL, не импортируют `App\Module\*\Domain\*`.
- Destructive changes — только через expand/contract.
- Каждая миграция требует обоснования (что и зачем меняется в данных).

### 3.10 Env переменные

- Новая переменная = обновлённый `.env`, `.env.example`, `docs/27-config-and-env.md`,
  `docs/34-deployment.md` (если влияет на prod).
- Секреты не коммитятся, попадают в `.env.local` локально и в systemd EnvironmentFile / vault на проде.

### 3.11 Deploy scripts

- Идемпотентны: повторный запуск не должен ломать систему.
- Любое изменение — обновлённый runbook (`docs/37-runbooks.md`) и/или `docs/34-deployment.md`.

### 3.12 Logging, tests, docs

- Обновляются **в том же PR**, что и код. «Потом дополним» = техдолг навсегда.

### 3.13 SEO-impact

- При изменении URL/route/slug/title/description/h1/canonical/sitemap/robots/redirects — обязателен
  SEO impact analysis и обновление `docs/26-seo-architecture.md` при необходимости.

### 3.14 Security-impact

- При изменении форм, админки, API, uploads, авторизации, CSRF, voters — обязательное обновление
  `docs/20-security-and-access-control.md` (если меняется контракт) и тестов на access control.

---

## 4. Required reading order перед изменениями

AI-агент **обязан** читать документацию по правильному порядку, а не хаотично.

### 4.1 Базовый обязательный набор перед любыми изменениями

1. `docs/00-overview.md`
2. `docs/01-product-purpose.md`
3. `docs/02-architecture.md`
4. `docs/03-project-structure.md`
5. `docs/04-layer-rules.md`
6. `docs/39-agent-guide.md` (этот документ)
7. `docs/40-cursor-rules.md`

### 4.2 Дополнительное чтение по затронутой области

| Область задачи                          | Дополнительные документы                                                                                                                |
|----------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| Контроллеры / HTTP / routing            | `docs/07-request-flow.md`, `docs/08-controller-architecture.md`, `docs/16-routing.md`                                                  |
| Application / Domain / Infrastructure   | `docs/09-application-layer.md`, `docs/10-domain-layer.md`, `docs/11-infrastructure-layer.md`, `docs/04-layer-rules.md`                  |
| Модули                                  | `docs/06-module-architecture.md`, `docs/43-module-development-guide.md`, `docs/45-roadmap-and-extension-points.md`                     |
| Admin                                   | `docs/12-admin-area.md`, `docs/20-security-and-access-control.md`                                                                       |
| Front                                   | `docs/13-front-area.md`, `docs/21-templates-and-twig.md`, `docs/22-frontend-assets.md`, `docs/26-seo-architecture.md`                   |
| API                                     | `docs/14-api-area.md`, `docs/20-security-and-access-control.md`, `docs/30-error-handling.md`                                            |
| Dev area                                | `docs/15-dev-area.md`, `docs/33-local-development.md`                                                                                   |
| Doctrine / Database / Migrations        | `docs/17-doctrine-and-database.md`, `docs/18-migrations.md`, `docs/05-domain-model.md`                                                  |
| Forms / DTO / Validation                | `docs/19-forms-dto-validation.md`, `docs/09-application-layer.md`                                                                       |
| Security                                | `docs/20-security-and-access-control.md`, `docs/27-config-and-env.md`, `docs/30-error-handling.md`                                      |
| SEO                                     | `docs/26-seo-architecture.md`, `docs/13-front-area.md`, `docs/16-routing.md`, `docs/21-templates-and-twig.md`                           |
| Cache / Redis                           | `docs/23-cache-and-redis.md`, `docs/11-infrastructure-layer.md`                                                                         |
| Messenger / Queues / Workers            | `docs/24-messenger-and-queues.md`, `docs/07-request-flow.md`, `docs/09-application-layer.md`                                            |
| Files / Uploads                         | `docs/25-files-and-uploads.md`, `docs/20-security-and-access-control.md`, `docs/36-backup-restore.md`                                   |
| Config / Env                            | `docs/27-config-and-env.md`, `docs/33-local-development.md`, `docs/34-deployment.md`                                                    |
| Logging / Healthchecks / Errors         | `docs/28-logging-observability.md`, `docs/29-healthchecks.md`, `docs/30-error-handling.md`, `docs/37-runbooks.md`                       |
| Testing / Quality                       | `docs/31-testing-strategy.md`, `docs/38-coding-standards.md`                                                                            |
| Docker / Local                          | `docs/32-docker-architecture.md`, `docs/33-local-development.md`                                                                        |
| Deployment / CI/CD / Backups            | `docs/34-deployment.md`, `docs/35-cicd.md`, `docs/36-backup-restore.md`, `docs/37-runbooks.md`                                          |
| Feature development                     | `docs/41-implementation-playbook.md`, `docs/42-feature-development-guide.md`                                                            |
| Troubleshooting                         | `docs/44-troubleshooting.md`, `docs/37-runbooks.md`, `docs/28-logging-observability.md`                                                 |
| Glossary                                | `docs/46-glossary.md`                                                                                                                   |

### 4.3 Правила чтения

- Если документа нет — отметить **documentation gap** в плане работы и не выдумывать архитектуру.
- Если документация **противоречит** коду — явно указать расхождение в ответе пользователю
  (что обновить: код или docs?), не молча выбирать сторону.
- Если задача затрагивает несколько зон — читать docs по **каждой** зоне.
- Если задача меняет архитектурное решение — проверить, нужен ли **ADR** (`docs/adr/*`).
- Если задача меняет deployment, config, env, миграции, SEO, security, public URL/API — обязателен
  **impact analysis** (раздел 7).
- Маленькая задача: можно сократить чтение до базового набора + relevant docs по модулю,
  но `docs/39-agent-guide.md` и `docs/40-cursor-rules.md` пропускать **нельзя**.

---

## 5. Классификация задачи перед реализацией

Перед любой работой агент классифицирует задачу. От класса зависит scope, риски и обязательные обновления.

| Класс изменения                | Затрагиваемые зоны                                       | Обязательно проверить                                                  | Документы к обновлению                                                       | Тесты                                                                |
|--------------------------------|----------------------------------------------------------|------------------------------------------------------------------------|------------------------------------------------------------------------------|----------------------------------------------------------------------|
| Новая фича                     | Domain, Application, UI, миграции, docs, tests           | Все impact-секции (раздел 7)                                           | feature guide, ADR (если решение значимое), `docs/05`, `docs/06`, релевантные | unit + integration + functional                                      |
| Багфикс                        | конкретный модуль                                        | regression tests, log noise                                             | `docs/44-troubleshooting.md` (если симптом частый)                            | regression test, воспроизводящий баг                                 |
| SEO-изменение                  | routing, Twig, sitemap, redirects, canonical             | `docs/26`, фактические URL/canonical/sitemap                            | `docs/26-seo-architecture.md`                                                | functional + SEO regression                                          |
| UI/Twig                        | templates, assets, view models                           | accessibility, mobile, SEO-теги                                         | `docs/21`, `docs/22`                                                         | functional snapshot где применимо                                    |
| Админка                        | `/admin` controllers, Vue SPA, voters                    | RBAC, audit log, CSRF                                                  | `docs/12`, `docs/20`                                                         | functional + security                                                |
| API                            | `/api/*` controllers, DTO, normalizer                    | backward compatibility, error format, exposed fields                    | `docs/14`, `docs/30`                                                         | API contract tests                                                   |
| Инфраструктурное               | Nginx, PHP-FPM, systemd, Redis, PostgreSQL config        | runbook, rollback                                                       | `docs/32`, `docs/34`, `docs/37`                                              | smoke + healthcheck                                                  |
| Миграция БД                    | `migrations/`, entities, repositories                    | expand/contract, data migration, rollback                              | `docs/17`, `docs/18`, `docs/05`                                              | migration test, repository test                                      |
| Doctrine mapping change        | entity attributes, indexes, types                         | реальная миграция, performance, indexes                                | `docs/17`                                                                    | repository tests + migration                                         |
| Cache/Redis                    | cache pools, key namespaces, invalidation                | stale data, secrets, TTL                                                | `docs/23`                                                                    | cache invalidation tests                                             |
| Messenger/worker               | message DTO, handlers, transport config                  | idempotency, retries, schema compatibility со старыми очередями         | `docs/24`, runbooks                                                          | handler unit + integration                                           |
| Deployment                     | `deploy/`, systemd, GitHub Actions                       | идемпотентность, rollback, maintenance mode                             | `docs/34`, `docs/35`, `docs/37`                                              | dry-run, staging                                                     |
| Refactor                       | внутренности модуля                                      | публичные контракты не меняются, тесты зелёные                          | внутренний readme модуля                                                     | существующие тесты + новые юнит                                      |
| Observability/logging          | Monolog channels, request_id, level                      | log noise, секреты в логах                                              | `docs/28`                                                                    | юнит, если кастомный formatter                                       |
| Security                       | voters, firewall, CSRF, uploads                          | RBAC матрица, regression                                                | `docs/20`, `docs/25`                                                          | security tests                                                       |
| Config/env                     | `.env*`, `config/packages/*`                             | обновление `.env.example`, deploy templates                             | `docs/27`                                                                    | config validation                                                    |
| Frontend assets                | Vite, Tailwind, Vue components                           | критические страницы, Lighthouse                                        | `docs/22`                                                                    | manual smoke + e2e где применимо                                     |
| Тестовое изменение             | `tests/`                                                 | flaky, изоляция                                                         | `docs/31`                                                                    | сами тесты                                                           |
| Документационное               | `docs/`                                                  | отсутствие противоречий с кодом                                         | соответствующий документ                                                     | —                                                                    |

> Для каждой задачи зафиксируй класс **до** начала кода — это меняет план и checklist.

---

## 6. Safe change protocol

Пошаговый протокол. **Каждый шаг обязателен**, кроме явно помеченных как опциональные.

1. **Понять задачу.** Перефразировать своими словами, найти неоднозначности, задать уточняющий вопрос
   пользователю, если они блокирующие.
2. **Определить тип изменения** (раздел 5).
3. **Найти затрагиваемые модули.** Полный список путей `src/Module/<X>/...`, конкретных файлов,
   роутов, миграций, шаблонов.
4. **Прочитать релевантные docs и ADR** (раздел 4).
5. **Проверить boundaries.** Затрагивает ли задача domain/application/infrastructure/UI?
   Не нарушает ли направления зависимостей?
6. **Составить короткий implementation plan.** Список изменений по файлам, обоснование, риски.
7. **Минимизировать blast radius.** Минимальный набор файлов, минимальный diff.
8. **Внести изменения минимальным diff.** Никаких unrelated правок и форматирования.
9. **Обновить tests.** Юнит, integration, functional — по необходимости.
10. **Обновить docs.** Использовать таблицу из раздела 16.
11. **Проверить migrations.** Нужна ли миграция? Корректна ли? Обратима ли?
12. **Проверить config/env impact.** `.env.example`, `docs/27`, deploy templates.
13. **Проверить deploy impact.** Нужно ли менять `deploy/`, systemd, Nginx, runbook?
14. **Проверить SEO impact.** Раздел 10.
15. **Проверить security impact.** Раздел 19.
16. **Проверить logging/observability.** Что логируется при ошибке? Не утекли ли секреты?
17. **Final review against architecture rules.** Раздел 3 + раздел 8.

### 6.1 Minimum safe scope

> **Minimum safe scope** = минимальный набор файлов, который полностью реализует задачу
> и не оставляет проект в нерабочем состоянии.

Правила:

- Меньше всегда лучше.
- Если изменение требует нового интерфейса в Domain — добавить **только** интерфейс и его реализацию,
  не расширять «на будущее».
- Если фикс одной строки — править одну строку, не «улучшать» соседние методы.
- Если требуется рефакторинг для безопасной реализации — выделить его в отдельный шаг и явно
  объявить пользователю.
- Если изменение требует миграции БД — миграция и кодовые изменения должны быть совместимыми
  (expand/contract), чтобы старая версия кода работала с новой схемой во время деплоя.

---

## 7. Change impact analysis

Для каждого изменения агент проходит checklist. Ответ «да» = обязательная проверка соответствующего раздела.

### 7.1 Checklist влияния

- [ ] Затрагивает ли роутинг (новые/изменённые/удалённые routes)?
- [ ] Затрагивает ли контроллеры (Front/Admin/API/Dev)?
- [ ] Затрагивает ли формы / DTO / валидаторы?
- [ ] Затрагивает ли Domain или Application слой?
- [ ] Затрагивает ли Doctrine schema (entity, mapping, indexes)?
- [ ] Требует ли миграцию БД?
- [ ] Затрагивает ли Twig / templates / view models?
- [ ] Затрагивает ли SEO metadata (title/description/h1/canonical/og:*/JSON-LD)?
- [ ] Затрагивает ли redirects / canonical / sitemap / robots.txt?
- [ ] Затрагивает ли cache (ключи, TTL, invalidation)?
- [ ] Затрагивает ли Redis (cache pools, lock — целевое; sessions/messenger в Redis не используются)?
- [ ] Затрагивает ли Messenger (DTO сообщений, transport, retry)?
- [ ] Затрагивает ли deploy (`deploy/`, systemd, Nginx, PHP-FPM)?
- [ ] Затрагивает ли Nginx (location, redirects, headers, rate limiting)?
- [ ] Затрагивает ли security (auth, CSRF, voters, uploads, public exposure)?
- [ ] Затрагивает ли observability (Monolog, log levels, healthcheck)?
- [ ] Затрагивает ли backups / rollback (новая категория данных, новый storage)?

### 7.2 Прямое vs косвенное влияние

- **Прямое** — файлы, которые меняются в этом PR.
- **Косвенное** — файлы, которые читают данные/контракт, изменённый в PR.
  Например, изменение поля entity = прямо затронут entity + миграция, косвенно — все use cases,
  читающие это поле, view models, sitemap, fixtures, тесты.

Косвенно затронутые файлы должны быть **прочитаны** (не обязательно изменены), чтобы убедиться,
что они не сломались.

---

## 8. Layer-by-layer implementation rules

### 8.1 Сводная таблица

| Слой                    | Назначение                                                    | Допустимые зависимости                                       | Запрещённые зависимости                                                   | Обновляется вместе с                          |
|-------------------------|---------------------------------------------------------------|--------------------------------------------------------------|---------------------------------------------------------------------------|-----------------------------------------------|
| Public/HTTP             | приём запросов через Nginx → PHP-FPM                          | конфиг Nginx, FastCGI                                        | бизнес-логика                                                              | `docs/32`, `docs/34`                          |
| Controllers             | парсинг запроса, вызов Application, формирование Response     | Application, Domain (для type hints), Symfony HTTP/Routing  | `EntityManagerInterface`, конкретные Doctrine repositories, бизнес-правила | роуты, DTO, тесты functional                  |
| Application / use cases | оркестрация сценариев                                         | Domain, repository interfaces, Validator constraints, PSR    | `Request/Response`, Twig, Doctrine `EntityManager`, конкретные repositories | DTO, validators, юнит-тесты                   |
| Domain                  | бизнес-инварианты, правила, агрегаты                          | PHP stdlib, `Symfony\Component\Uid`, Doctrine attributes     | Symfony HTTP, Doctrine ORM (вызовы), Twig, Redis, FS, Mailer               | юнит-тесты domain                             |
| Infrastructure          | реализация интерфейсов (Doctrine, Redis, Mailer, Storage)     | Application + Domain + любые внешние библиотеки              | определение бизнес-правил                                                  | integration tests                             |
| Persistence / Doctrine  | репозитории, маппинг, миграции                                 | Doctrine ORM/DBAL                                            | бизнес-сценарии                                                            | миграции, integration tests                   |
| Presentation / Twig     | рендеринг view model                                          | Twig, view models                                            | `EntityManager`, Repositories, Doctrine queries, бизнес-логика             | view models, snapshot/functional tests        |
| Admin                   | админка (Symfony controllers + Vue SPA)                       | Application, voters                                          | прямые SQL, Doctrine repositories напрямую                                 | RBAC docs, voters tests                       |
| API                     | публичные/admin REST endpoints                                | Application, normalizers, DTO                                | отдача Doctrine entities напрямую                                          | API contract tests                            |
| Console                 | CLI команды                                                   | Application, Symfony Console                                 | бизнес-логика inline                                                       | юнит-тесты команд                             |
| Workers / Messenger     | асинхронные сценарии                                          | Application, Messenger                                       | бизнес-логика inline, длинные транзакции                                   | handler tests, runbooks                       |
| Frontend assets         | Vite/Tailwind/Vue                                             | браузерные API                                                | прямой доступ к БД/Redis                                                   | `docs/22`                                     |
| Deploy / scripts        | bash, systemd                                                 | OS, systemd, php-fpm                                         | прямой доступ к Domain/Application code                                    | `docs/34`, `docs/37`                          |
| Tests                   | unit/integration/functional                                   | соответствующий слой + test fixtures                          | глобальное состояние                                                       | сами тесты + `docs/31`                        |
| Docs                    | source of truth для архитектуры                               | актуальные ссылки                                             | расхождения с кодом                                                        | при каждом значимом изменении                 |

### 8.2 Public/HTTP

- **Назначение:** приём запросов, terminate TLS, проксирование в PHP-FPM.
- **Типичные изменения:** новые `location`, rate limit, security headers.
- **Типичные ошибки:** ослабление security headers, удаление `try_files`, нарушение redirects.
- **Тестировать:** smoke + healthcheck на staging.

### 8.3 Controllers

- **Назначение:** тонкий адаптер HTTP → Application.
- **Типичные изменения:** новый endpoint, новый action, изменение валидации входа.
- **Типичные ошибки:** инжект `EntityManager`, бизнес-логика в action, прямой Doctrine query.
- **Тестировать:** functional test (контроллер + DI + роуты).

### 8.4 Application / use cases

- **Назначение:** оркестрация сценария, координация Domain + Infrastructure через interfaces.
- **Типичные изменения:** новый command/query handler, новый use case.
- **Типичные ошибки:** прямой доступ к Doctrine, обращение к `Request`, утечка инфраструктуры.
- **Тестировать:** unit с моками repository interfaces.

### 8.5 Domain

- **Назначение:** инварианты, правила, агрегаты, value objects.
- **Типичные изменения:** новый value object, новый метод агрегата, доменное событие.
- **Типичные ошибки:** утечка Symfony/Doctrine, geзить «бога-сущность».
- **Тестировать:** pure unit без БД.

### 8.6 Infrastructure

- **Назначение:** реализация всего, что выходит за границы процесса.
- **Типичные изменения:** новый Doctrine repository, HTTP-клиент, file storage adapter.
- **Типичные ошибки:** перекладывание бизнес-логики в Doctrine repository.
- **Тестировать:** integration test с реальной зависимостью (или testcontainer).

### 8.7 Persistence / Doctrine

- **Назначение:** маппинг, миграции, индексы.
- **Типичные изменения:** новая таблица, индекс, изменение типа поля.
- **Типичные ошибки:** прямой destructive change, отсутствие индекса под FK, missing default.
- **Тестировать:** миграция up/down, repository integration test.

### 8.8 Presentation / Twig

- **Назначение:** рендеринг подготовленного view model.
- **Типичные изменения:** новый блок, новая страница, изменение разметки.
- **Типичные ошибки:** Doctrine query в шаблоне, бизнес-вычисления в Twig, ломанные SEO-теги.
- **Тестировать:** functional test страницы + ручной просмотр.

### 8.9 Admin

- **Назначение:** редакторы контента, RBAC, аудит критичных действий.
- **Типичные изменения:** новый CRUD, новое поле, новая роль.
- **Типичные ошибки:** утечка admin-логики во front, отсутствие voter, бизнес-правила в Vue SPA.
- **Тестировать:** functional + voter + audit.

### 8.10 API

- **Назначение:** контракт для внешних клиентов и admin SPA.
- **Типичные изменения:** новый endpoint, новое поле в response.
- **Типичные ошибки:** отдача Doctrine entity напрямую, breaking change без версии, утечка stack trace.
- **Тестировать:** contract tests, snapshot response.

### 8.11 Console commands

- **Типичные ошибки:** долгий `execute()` с inline бизнес-логикой, нет логирования, нет exit code.
- **Тестировать:** unit с подменой Application.

### 8.12 Workers / Messenger

- **Типичные ошибки:** не идемпотентный handler, долгие транзакции, изменение DTO без обратной совместимости.
- **Тестировать:** handler unit + integration на in-memory transport.

### 8.13 Frontend assets

- **Типичные ошибки:** удаление SEO-тегов, layout shift, удаление a11y атрибутов.
- **Тестировать:** Vite build, ручной просмотр критических страниц.

### 8.14 Deploy / scripts

- **Типичные ошибки:** non-idempotent скрипт, неучтённый `composer install` в prod, не учли кэш Symfony.
- **Тестировать:** dry-run + staging.

### 8.15 Tests

- **Типичные ошибки:** flaky, общие fixtures, отсутствие изоляции БД.
- **Тестировать:** запустить полный suite после изменений.

### 8.16 Docs

- **Типичные ошибки:** устаревшие пути, ссылки на несуществующие документы, расхождение с кодом.
- **Тестировать:** ручной review, проверка ссылок.

---

## 9. Rules for database/schema changes

### 9.1 Когда нужна миграция

Любое изменение схемы PostgreSQL: новая таблица, новое поле, индекс, тип, ограничение, default,
переименование, удаление. Никаких изменений «на лету» через `EntityManager` или ручной SQL на проде.

### 9.2 Как писать миграцию

- Имя — `Version<UTC timestamp>.php`, генерация через `bin/console doctrine:migrations:diff`.
- Метод `up()` — целевая схема, `down()` — обратная, если возможно.
- Только DBAL, никаких импортов `App\Module\*\Domain`.
- Описание (`getDescription()`) обязательно: что и зачем меняем.
- Для data migration — отдельная миграция, отделённая от schema migration, с явной батч-обработкой
  (LIMIT/OFFSET, курсор) для больших таблиц.

### 9.3 Expand/contract strategy

Для destructive изменений (удаление колонки, переименование, изменение типа):

1. **Expand:** добавить новое (новая колонка/таблица), кодом писать в обе схемы.
2. **Migrate data:** перенос данных между старой и новой схемой.
3. **Switch reads:** код читает из новой схемы.
4. **Contract:** удалить старое (отдельная миграция, после полного релиза предыдущих шагов).

Каждый шаг — **отдельный релиз**.

### 9.4 Влияние на существующие данные

Перед миграцией:

- Сколько строк в таблице?
- Сколько `NULL` в колонке, которую делаем `NOT NULL`?
- Какие default значения для новых колонок?
- Есть ли FK, которые сломаются?
- Сколько времени миграция выполняется на prod-объёме?

### 9.5 Влияние на production

- Долгие миграции (минуты на блокирующих DDL) — требуют maintenance mode или `CREATE INDEX CONCURRENTLY`.
- Удаление колонки — только через expand/contract.
- Изменение типа — обычно через expand/contract (новая колонка → перенос → swap → удаление).

### 9.6 Rollback

- Schema migration без data — `down()` обязателен.
- Data migration — обычно не откатывается, нужен бэкап перед запуском.
- Любая миграция, ломающая совместимость со старым кодом, — требует expand/contract, чтобы старая
  версия кода работала во время и после миграции.

### 9.7 Что обновлять вместе с миграцией

- Doctrine entity (mapping).
- Domain `*RepositoryInterface` и `Doctrine*Repository` — добавить/обновить методы.
- Fixtures.
- Юнит-тесты domain (если меняется правило).
- Integration tests repository.
- `docs/05-domain-model.md` (если меняется доменная модель).
- `docs/17-doctrine-and-database.md` (если новая таблица/индекс заслуживают упоминания).

### 9.8 Индексы, nullable, default, enum

- FK всегда сопровождается индексом.
- Поле, по которому фильтруется список, требует индекс (B-tree/GIN/JSONB index по контексту).
- `NOT NULL` без default = риск сломать insert. Всегда объяснять явно.
- Enum-подобные поля — в нашем проекте обычно `VARCHAR + CHECK` или `smallint + map`. Не использовать
  PostgreSQL `ENUM` (миграция типа болезненна).

### 9.9 Workers/jobs, завязанные на схему

- Если поле читается worker'ом из таблицы или message DTO, изменение схемы должно быть
  **обратно совместимо** с уже стоящими в очереди задачами.
- Если совместимость невозможна — drain очереди перед миграцией (документируется в runbook).

### 9.10 «Before touching database» checklist

- [ ] Прочитан `docs/17-doctrine-and-database.md` и `docs/18-migrations.md`.
- [ ] Проверена текущая схема (актуальные миграции применены локально).
- [ ] Понятна цель изменения схемы (что и зачем).
- [ ] Оценено влияние на существующие данные.
- [ ] Решено, нужна ли expand/contract стратегия.
- [ ] Спроектирован `down()` или принято решение, почему он невозможен.
- [ ] Учтены индексы и FK.
- [ ] Учтены workers/queues, читающие эту таблицу.
- [ ] Обновлены entities, repositories, fixtures.
- [ ] Добавлены/обновлены тесты.
- [ ] Обновлены docs.
- [ ] Проверена сборка и `bin/console doctrine:migrations:migrate --dry-run`.

---

## 10. Rules for SEO-related changes

### 10.1 Когда изменение SEO-sensitive

Любое из:

- меняется URL/route/префикс/host/локаль;
- меняется slug страницы или способ его генерации;
- меняется title / meta description / h1 / OpenGraph / JSON-LD;
- меняется canonical (значение, отсутствие, conditional);
- меняется breadcrumbs;
- меняется sitemap.xml (структура или включаемые страницы);
- меняется robots.txt;
- меняются redirects (301/302/308);
- меняется логика hreflang.

### 10.2 Что проверять

| Что меняется             | Что проверить                                                                                                                |
|--------------------------|------------------------------------------------------------------------------------------------------------------------------|
| URL                      | redirect 301 со старого на новый, обновление sitemap, проверка внутренних ссылок                                              |
| routing                  | приоритеты роутов (`docs/16`), нет конфликтов, sitemap отражает изменения                                                     |
| slug                     | стабильность slug (он не должен меняться при типовых редактированиях), 301-редирект при изменении                              |
| title / description / h1 | длина (title 50–60, description 140–160), уникальность для страниц, корректность для пагинации/фильтров                        |
| canonical                | один canonical на страницу, абсолютный URL, корректный для пагинации/utm                                                      |
| breadcrumbs              | соответствие реальной иерархии, JSON-LD breadcrumbs                                                                          |
| sitemap                  | валидный XML, корректные lastmod, не выходить за лимит 50k URL/файл                                                          |
| robots.txt               | случайный `Disallow: /` запрещён, `noindex` не должен попадать на ключевые страницы                                          |
| redirects                | нет циклов, нет цепочек 3+, корректный код (301 для постоянных)                                                              |

### 10.3 Что нельзя без явного решения

- Менять публичный URL без 301 redirect.
- Удалять `canonical` или менять домен в нём.
- Добавлять `noindex`, `nofollow` на страницы, которые приносят трафик.
- Удалять страницу из `sitemap.xml`, если она остаётся доступной.
- Менять структуру URL для всего раздела «по пути».
- Изменять формат генерации slug для существующих страниц.

### 10.4 Do / Don't

**Do:**
- При переименовании раздела добавить 301 со старого URL.
- Подтвердить SEO-чистоту через staging до релиза.
- Документировать SEO-impact в PR description.
- Тестировать рендеринг title/description/canonical.

**Don't:**
- «По-быстрому» поменять route и забыть про redirect.
- Менять template `head` без проверки meta-тегов.
- Доверять автогенерации slug на критичных страницах.
- Включать `noindex` в production без подтверждения.

### 10.5 Документирование SEO-impact

В PR description:

```
## SEO impact
- Меняется URL: /uslugi/montazh -> /services/installation
- Добавлен 301 redirect (см. RedirectKernelSubscriber + миграция Redirect)
- Sitemap пересобран: 12 URL заменены
- Canonical обновлён в template services/index.html.twig
- Search Console: уведомить о изменении после релиза
```

---

## 11. Rules for admin/CMS changes

### 11.1 Общие правила

- Админка живёт в `/admin`, отдельный firewall, отдельные voters, отдельный CSRF.
- Админ-контроллеры **тонкие**, бизнес-логика — в Application.
- Vue SPA общается с backend через `/api/admin/*`, не через формы.
- Любая destructive операция (delete, restore, force publish) — через подтверждение и audit log.

### 11.2 Новые сущности / поля

- Новая сущность = новый модуль или часть существующего, с domain/application/infrastructure/UI.
- Новое поле = entity + миграция + DTO (admin API) + Vue форма + валидация + тесты.
- SEO-поля (title, slug, canonical, og_image) — учитывать всегда, если сущность публичная.

### 11.3 Валидация

- Через Validator constraints на DTO. Никаких inline-проверок в контроллере.
- Для уникальности — отдельный Constraint с DI на repository interface.

### 11.4 Защита

- Каждый admin route проверяется voter'ом (`is_granted('ADMIN_X', subject)`).
- Никаких «открытых» эндпоинтов в `/admin` или `/api/admin`.
- CSRF включён по умолчанию.

### 11.5 Логирование

- Действия create/update/delete/publish/unpublish/restore — в audit log с user_id, entity_id, action,
  before/after diff (для критических полей).

### 11.6 UX редактора

- Не ломать сохранённые черновики при изменении схемы (миграция данных).
- Не убирать поля без обоснования и переноса данных.
- Сохранять обратную совместимость WYSIWYG/блочного редактора, если такой есть.

### 11.7 Документация для редакторов

- Если меняется поведение редактора — обновить `docs/CONTENT_EDITOR_GUIDE.md` и/или `docs/ADMIN_GUIDE.md`.

---

## 12. Rules for API changes

### 12.1 Когда изменение API-breaking

- Удаление/переименование endpoint'а или поля.
- Изменение типа поля.
- Изменение формата ошибок.
- Ужесточение валидации (новое required поле).
- Изменение поведения существующего endpoint'а.

### 12.2 Версионирование

- Публичное API — версионируется через префикс `/api/v1/...`.
- Admin API (`/api/admin/...`) — внутренний, breaking изменения возможны, но фиксируются в `docs/14`.
- Breaking change в публичном API без новой версии — запрещён.

### 12.3 Request DTO

- Все входы валидируются через DTO + Validator.
- Не принимать «свободный JSON» в контроллере.
- Никаких `Request::get()` для бизнес-параметров.

### 12.4 Response DTO

- Ответ — это **отдельный DTO** или нормализованный view model, не Doctrine entity.
- Запрещено `return $this->json($entity)` для Doctrine entity.
- Поля «внутренние» (hash, internal flags) — не отдаются.

### 12.5 Логирование ошибок

- 4xx — обычно warning/notice, без stack trace в response.
- 5xx — error, со stack trace в логе, без stack trace в response.
- Формат ошибок единый, см. `docs/30-error-handling.md`.

### 12.6 Backward compatibility

- Новое поле в response — допустимо.
- Удаление поля — breaking.
- Новый required параметр — breaking.
- Новый optional параметр с default — допустимо.

---

## 13. Rules for cache/Redis changes

### 13.1 Когда можно добавлять cache

- Чтение тяжёлое и часто повторяется.
- Данные стабильны (TTL имеет смысл).
- Есть стратегия инвалидации.

### 13.2 Когда **нельзя**

- Персональные данные пользователя в shared cache.
- Секреты, токены, сессии — это не cache, это разные пулы.
- Данные, для которых stale = корректно нельзя (биллинг, заказы).

### 13.3 Cache key

- Префикс модуля: `cache.<module>.<entity>.<id>` или `cache.<module>.<query>.<hash>`.
- Версионирование ключа (`v1`, `v2`) при изменении формата.
- Никаких user-input напрямую в ключе без хэша.

### 13.4 Invalidation

- На write через repository / use case инвалидируется соответствующий ключ.
- На массовое изменение — bump версии в ключе.
- TTL обязателен (даже на «вечный» cache — fallback).

### 13.5 SEO-страницы

- Кэширование рендера публичных страниц допустимо, но `Cache-Control` и canonical должны оставаться корректными.
- При публикации/изменении страницы — инвалидация HTML-cache + sitemap-cache.

### 13.6 Stale data

- Документировать допустимый stale window (5 секунд / 1 минута / 1 час).
- При критичных операциях — `cache.delete` сразу после write.

### 13.7 Тесты

- Юнит-тест на ключ и invalidation.
- Integration test, что после write следующий read возвращает свежие данные.

---

## 14. Rules for Messenger/worker changes

### 14.1 Не сломать retry

- Retry стратегия — конфигурируется на transport, а не в handler.
- Handler не должен «глотать» исключения, чтобы Messenger делал retry.

### 14.2 Не получить дубли

- Идемпотентный handler: повторное выполнение с тем же входом не должно создавать дубли.
- Идемпотентность через ключ (`message_id` + dedup table) или через состояние сущности (`status`).

### 14.3 Не потерять задачи

- Не использовать `dispatch` без транзакционной outbox-pattern, если задача обязана быть выполнена.
- Doctrine transport: `dispatch` в той же транзакции, что и сохранение entity.

### 14.4 Partial failures

- Сохранять прогресс (для batch-задач).
- Чётко определять «retryable vs non-retryable» exceptions.

### 14.5 Idempotency

- Каждый handler в комментарии класса описывает idempotency contract.

### 14.6 Message DTO

- Простые, сериализуемые, immutable.
- Никаких Doctrine entities внутри сообщения.
- Ключевые поля: id сущности, не сама сущность.

### 14.7 Логирование

- В каждом handler в начале — `logger->info('handling X', ['job_id' => ..., 'entity_id' => ..., 'user_id' => ...])`.
- На исключении — `logger->error(...)` с контекстом.

### 14.8 Транзакции

- Транзакция вокруг минимальной части работы, не вокруг всего handler.
- Не комитить и сразу `dispatch` — это потеря (сообщение уйдёт даже если транзакция откатится).
  Использовать outbox или Doctrine transport.

### 14.9 Не делать long-running бизнес-логику inline

- Длинные расчёты — делегируются Application service, handler — тонкий.

### 14.10 Документация

- Каждый новый handler — упомянут в `docs/24-messenger-and-queues.md` и в runbook (`docs/37`).

---

## 15. Rules for deployment-related changes

### 15.1 Когда менять что

| Что меняется                | Когда менять                                                                          |
|-----------------------------|---------------------------------------------------------------------------------------|
| Dockerfile (`docker/php/`)  | новая PHP extension, новый системный пакет для local dev                              |
| docker-compose              | новый сервис для local dev (Redis, MailHog, MinIO)                                    |
| Nginx                       | новый location, security header, rate limit, изменение upstream                       |
| PHP-FPM                     | смена pm-параметров, opcache, memory_limit, slowlog                                   |
| systemd                     | новый worker, изменение Restart/RestartSec, EnvironmentFile                            |
| GitHub Actions              | новый job, изменение matrix, новые secrets                                            |
| deploy scripts              | изменение порядка релиза, maintenance mode, cache warmup, asset build                 |

### 15.2 Идемпотентность

- Скрипт можно запустить 5 раз подряд — система остаётся в том же состоянии.
- Никаких безусловных `mkdir`, `chown -R`, `git pull` без проверки состояния.
- Использование `set -euo pipefail` обязательно.

### 15.3 Документация порядка деплоя

`docs/34-deployment.md` отражает реальный порядок шагов:

1. pre-deploy hooks (бэкап, maintenance);
2. `composer install --no-dev --optimize-autoloader`;
3. asset build (Vite);
4. `bin/console cache:clear` + warmup;
5. миграции (с учётом expand/contract);
6. перезапуск PHP-FPM + workers;
7. post-deploy hooks (smoke, healthcheck);
8. снятие maintenance mode.

### 15.4 Rollback

- Каждый deploy подразумевает release directory + symlink. Rollback = переключение symlink.
- Миграции учитываются отдельно: rollback кода не означает rollback схемы.

### 15.5 Maintenance mode

- Включается на время destructive миграций.
- Реализован через Nginx + статический HTML.

### 15.6 Cache warmup, asset build, permissions

- Cache warmup — обязателен после `cache:clear`.
- Asset build — на CI или на сервере, один способ задокументирован.
- Permissions — `var/`, `var/cache/`, `var/log/` доступны www-data.

### 15.7 «Before touching deployment» checklist

- [ ] Прочитан `docs/34-deployment.md`, `docs/37-runbooks.md`.
- [ ] Понятен текущий поток деплоя.
- [ ] Скрипт идемпотентен.
- [ ] Документирован порядок шагов.
- [ ] Rollback продуман.
- [ ] Учтены миграции (expand/contract если нужно).
- [ ] Cache warmup и asset build не сломаны.
- [ ] Permissions проверены.
- [ ] Maintenance mode рассмотрен.
- [ ] Smoke + healthcheck шаги работают.

---

## 16. Documentation obligations

### 16.1 Что обновлять при изменениях

| Что изменилось                              | Что обновить                                                                                |
|---------------------------------------------|---------------------------------------------------------------------------------------------|
| Архитектура (boundaries, слои)              | `docs/02-architecture.md`, `docs/04-layer-rules.md`, при значимом решении — ADR              |
| Доменная модель                             | `docs/05-domain-model.md`, `docs/10-domain-layer.md`                                         |
| Структура модулей                           | `docs/06-module-architecture.md`, `docs/43-module-development-guide.md`                      |
| Roadmap / extension points                  | `docs/45-roadmap-and-extension-points.md`                                                    |
| Запуск / install / local dev                | `docs/33-local-development.md`, `docs/INSTALL.md`, `README.md`                                |
| Deployment / VPS                            | `docs/34-deployment.md`, `docs/37-runbooks.md`, `docs/35-cicd.md`, `docs/DEPLOY.md`          |
| Backups                                     | `docs/36-backup-restore.md`                                                                  |
| Database schema                             | `docs/17-doctrine-and-database.md`, `docs/18-migrations.md`, `docs/05-domain-model.md`       |
| API контракт                                | `docs/14-api-area.md`, `docs/30-error-handling.md`                                           |
| SEO behavior                                | `docs/26-seo-architecture.md`                                                                |
| Admin behavior / редактор                   | `docs/12-admin-area.md`, `docs/ADMIN_GUIDE.md`, `docs/CONTENT_EDITOR_GUIDE.md`               |
| Security / RBAC                             | `docs/20-security-and-access-control.md`, `docs/ROLES.md`                                    |
| Cache / Redis                               | `docs/23-cache-and-redis.md`                                                                 |
| Messenger / queues                          | `docs/24-messenger-and-queues.md`, `docs/37-runbooks.md`                                     |
| Files / uploads                             | `docs/25-files-and-uploads.md`, `docs/UPLOAD_SECURITY.md`                                    |
| Env / config                                | `docs/27-config-and-env.md`, `.env.example`, `docs/DEPLOY_VARIABLES.md`                      |
| Logging / observability                     | `docs/28-logging-observability.md`                                                           |
| Healthchecks                                | `docs/29-healthchecks.md`                                                                    |
| Error handling                              | `docs/30-error-handling.md`                                                                  |
| Testing strategy                            | `docs/31-testing-strategy.md`, `docs/TESTING.md`                                              |
| Coding standards                            | `docs/38-coding-standards.md`                                                                |
| AI / Cursor правила                         | `docs/39-agent-guide.md`, `docs/40-cursor-rules.md`                                          |
| Troubleshooting                             | `docs/44-troubleshooting.md`                                                                 |

### 16.2 Когда нужен ADR

- Смена базового стека (БД, кэш, очередь, фронтенд-инструмент).
- Изменение архитектурной границы (например, переход на отдельный микросервис).
- Принятие правила, которое нельзя нарушать (например, «admin SPA общается только через `/api/admin`»).
- Любое решение, которое будут оспаривать через 6 месяцев.

ADR создаются в `docs/adr/000X-...md` с шаблоном (Context, Decision, Consequences, Status).

---

## 17. Testing obligations

### 17.1 Что должно быть покрыто

- **Unit tests** — Domain (агрегаты, value objects, доменные сервисы), Application (use cases с моками).
- **Integration tests** — Doctrine repositories, Messenger handlers с in-memory transport, Mailer fakes.
- **Functional/controller tests** — каждый публичный route, admin route, API endpoint.
- **Application service tests** — все use cases с моками портов.
- **Domain tests** — каждое доменное правило.
- **Repository tests** — кастомные методы поиска, граничные случаи.
- **API tests** — happy path + 4xx/5xx + auth/permission.
- **Security tests** — voters, firewall, CSRF, access control matrix.
- **Validation tests** — DTO + Constraint, success + ошибки.
- **Filename/path safety tests** — для uploads и любого I/O с user input.
- **Cache behavior tests** — write → invalidate → read.
- **Messenger/worker tests** — handler unit + acknowledge/retry.
- **SEO regression tests** — функциональные на критичные URL и meta-теги.
- **Config validation tests** — `bin/console debug:container`, `lint:yaml`, `lint:twig`.

### 17.2 Migration-related thought process

- Запустить миграцию up на тестовой БД.
- Загрузить fixtures.
- Запустить миграцию down (если есть).
- Repository test проходит на новой схеме.

### 17.3 Правило: если тестов нет — объясни почему

Если изменение **не покрывается** тестами, агент обязан явно написать в ответе:

> Тесты не добавлены, потому что: <причина>. Risk: <уровень>. Mitigation: <ручная проверка / e2e>.

«Это просто docs», «это конфиг», «это форматирование» — приемлемые причины. «Забыл», «потом» — нет.

---

## 18. Logging/observability obligations

### 18.1 Что логировать

- Старт/успех/ошибка use case (на уровне Application).
- Старт/успех/ошибка Messenger handler.
- HTTP 5xx с trace.
- Security events (логин, неудачный логин, изменение роли, доступ запрещён).
- Admin destructive actions.
- Внешние HTTP-вызовы (URL, status, latency).

### 18.2 Не логировать

- Пароли, токены, API ключи, cookies, JWT, секреты из `.env`.
- Полные тела request с PII в production logs.
- Карточные данные (даже если их нет — на всякий случай в фильтре).

### 18.3 Контекст

Каждый лог содержит, где применимо:
`request_id`, `user_id`, `entity_id`, `module`, `action`, `job_id`, `correlation_id`.

### 18.4 Когда добавлять новые log events

- Появилось новое security/admin/worker действие.
- Появился новый внешний интегратор (HTTP-клиент).
- Возникла регрессия, которую сложно диагностировать без лога.

### 18.5 User-facing errors vs internal logs

- Пользователь видит человеческое сообщение + `request_id`.
- Лог содержит trace, контекст, исходное исключение.

### 18.6 Не создавать log noise

- `info` — события, которые потом ищут.
- `debug` — детали для разработки, отключены в prod по уровню.
- Не логировать каждую успешную миллисекундную операцию.

---

## 19. Security obligations

### 19.1 Базовый набор

- **Input validation** — на границе (DTO + Validator), а не в Domain.
- **URL validation** — для любых внешних URL (avatars, webhook callbacks).
- **CSRF** — включён на формах и `/api/admin/*` (через Origin/Referer + token).
- **Access control** — voters для каждого нетривиального действия.
- **Role checks** — через `is_granted`, не через `getUser()->hasRole()` в коде.
- **No unsafe shell execution** — никаких `shell_exec`, `exec`, `system` без airtight reasoning.
- **No path traversal** — все пути через `Path::isAbsolute`, allow-list директорий, нормализация.
- **Safe file uploads** — MIME sniffing, allow-list расширений, ограничение размера, изолированное хранение.
- **No exposure of Redis/Postgres** — bind на 127.0.0.1, firewall.
- **Secret handling** — в `.env.local` или systemd EnvironmentFile, не в git.
- **Safe file lifecycle** — тmp → final move, cleanup на failure.
- **Safe admin actions** — confirmation для destructive, audit log.
- **Safe API responses** — без stack trace, без internal IDs если не intended.
- **No secrets in logs** — фильтр Monolog Processor.
- **No secrets in git** — `.gitignore` для `.env.local*`, secret scan на CI.
- **Secure Nginx defaults** — HSTS, X-Frame-Options, X-Content-Type-Options, CSP (где применимо).
- **Secure cookies/session** — `Secure`, `HttpOnly`, `SameSite=Lax/Strict`.
- **Careful CORS** — никакого `*` для admin API, allow-list по origin.

### 19.2 Что обязательно проверить при security-impact задачах

- RBAC матрица: кто что может?
- Все новые endpoints проверяются voter'ом или firewall?
- Есть ли rate limit на чувствительные действия (login, password reset)?
- Сохраняются ли security headers?
- Работает ли CSRF на новых формах?

---

## 20. Anti-patterns

### Запрещено

- Бизнес-логика в контроллерах.
- Infrastructure (Doctrine, Redis, Symfony bundles) импортируется в Domain.
- Doctrine entities возвращаются из API напрямую.
- SQL пишется хаотично в контроллерах/сервисах.
- Большой рефакторинг ради маленькой фичи.
- Перенос боундари слоёв «по пути» без обоснования.
- Новая env переменная без документации в `.env.example` и `docs/27`.
- Изменение DB schema без миграции и без reasoning.
- `shell_exec`, `exec`, `system` без airtight justification.
- Игнорировать SEO impact при изменении URL/templates.
- Игнорировать cache invalidation при write.
- Игнорировать cleanup/backup/deploy impact.
- Не обновлять tests/docs. **Если изменение меняет фактическое поведение проекта (новое поле Entity, изменение контроллера, смена кэш-стратегии, новый middleware, изменение config), документ из таблицы в [38-coding-standards#документация-обязательная-актуализация-в-том-же-pr](38-coding-standards.md#документация--обязательная-актуализация-в-том-же-pr) обновляется в том же PR.** Это не «потом».
- Менять unrelated файлы (форматирование, импорты, неименование).
- Over-refactoring «потому что код страшный».
- Добавлять зависимости (composer/npm) без необходимости.
- God services / god controllers.
- Смешивать admin/frontend/API концерны в одном контроллере или сервисе.

### Anti-pattern: контроллер с бизнес-логикой

**Don't:**

```php
public function publish(int $id, EntityManagerInterface $em): Response
{
    $page = $em->find(Page::class, $id);
    if ($page->getStatus() === 'draft') {
        $page->setStatus('published');
        $page->setPublishedAt(new \DateTimeImmutable());
        $em->flush();
    }
    return $this->redirectToRoute('admin_page_index');
}
```

**Do:**

```php
public function publish(string $id, PublishPageHandler $handler): Response
{
    $handler(new PublishPageCommand($id, $this->getUser()->getId()));
    return $this->redirectToRoute('admin_page_index');
}
```

### Anti-pattern: god service

**Don't:** `PageService` с методами `create/update/delete/publish/archive/restore/exportToCsv/importFromXml/sendNewsletter`.

**Do:** отдельные command handlers по сценариям, отдельные query handlers по чтению, отдельные сервисы по техническим задачам (export, import, newsletter).

### Anti-pattern: утечка Doctrine

**Don't:** возвращать `array<Page>` из API контроллера, где `Page` — Doctrine entity.

**Do:** `PageListResponse` (DTO) с явными полями.

---

## 21. Common mistakes by AI agents

| Ошибка                                                       | Что вместо этого                                                                                          |
|--------------------------------------------------------------|------------------------------------------------------------------------------------------------------------|
| Over-refactoring (переписать соседние классы «по пути»)       | Отдельный refactor PR с явным scope                                                                        |
| Утечка инфраструктуры в Domain                                | Использовать interface в Domain, реализацию в Infrastructure                                                |
| Скрытый coupling (статика, глобальные state)                  | DI, явные параметры                                                                                        |
| Незавершённая миграция (только entity, без миграции)          | `bin/console doctrine:migrations:diff` + ручная правка                                                     |
| Нет rollback thinking                                         | Описать `down()` и план отката кода                                                                        |
| Пропущены docs updates                                        | Таблица из раздела 16                                                                                      |
| Пропущены тесты                                               | Раздел 17                                                                                                  |
| Неверные assumptions о Symfony conventions                    | Проверить `docs/03`, `docs/08`                                                                              |
| Неверные assumptions о Doctrine lifecycle                     | Проверить `docs/17`                                                                                         |
| Игнорирование Messenger semantics                             | Прочитать `docs/24`, добавить idempotency                                                                  |
| Сломанные install/deploy скрипты                              | dry-run, staging                                                                                           |
| Бездумные изменения compose файлов                            | Понять, какой сервис зависит от какого                                                                     |
| Сломанные SEO URL                                             | Раздел 10                                                                                                   |
| Изменение route без 301                                       | Добавить redirect entity или Nginx-rule                                                                     |
| Забытый env template                                          | `.env.example` + `docs/27`                                                                                  |
| `// TODO` вместо завершения                                   | Сделать сейчас или явно вынести в follow-up issue                                                          |
| Форматирование unrelated файлов                               | Только файлы, которые меняются по сути задачи                                                              |

---

## 22. Definition of done

Изменение готово, когда **все** пункты выполнены:

- [ ] Architecture rules соблюдены (раздел 3).
- [ ] Layer boundaries соблюдены (раздел 8).
- [ ] Тесты обновлены или есть явное обоснование почему нет.
- [ ] Документация обновлена (таблица 16.1).
- [ ] Config/env обновлён (`.env.example`, `docs/27`) если применимо.
- [ ] Миграции созданы и протестированы (раздел 9) если применимо.
- [ ] Логирование добавлено где нужно (раздел 18).
- [ ] Security implications проверены (раздел 19).
- [ ] SEO impact проверен (раздел 10).
- [ ] Cache impact проверен (раздел 13).
- [ ] Deploy impact проверен (раздел 15).
- [ ] Никаких unrelated файлов в diff.
- [ ] Изменение минимальное и объяснимое.
- [ ] Rollback / migration risk учтён.
- [ ] Final diff просмотрен.
- [ ] Локально прошли: `composer validate --strict`, `composer check:syntax`,
      `vendor/bin/php-cs-fixer fix --dry-run --diff`, `vendor/bin/phpstan analyse`,
      `vendor/bin/rector process --dry-run`, `vendor/bin/phpunit`, `npm run build`
      (где применимо к изменению).

---

## 23. Escalation rules

Агент **обязан остановиться** и не продолжать blindly, когда:

1. Не понятна цель задачи на 100%, и угадывание создаёт риск.
2. Затрагивается архитектурная граница без чёткого решения — предложить варианты пользователю.
3. Нужно зафиксировать **ADR**, потому что решение значимое и долгосрочное.
4. Изменение требует **destructive миграции** без понятного транзитного плана.
5. Изменение меняет **публичный URL** или route без подтверждения SEO-impact.
6. Изменение меняет **публичный API контракт** без согласования backward compatibility.
7. Изменение меняет **security defaults** (firewall, CSRF, voters) без явного reasoning.
8. Изменение меняет **deploy defaults** (Nginx, systemd, PHP-FPM) без понимания runtime impact.
9. Требуется **новая зависимость** (composer/npm) без обоснования.
10. Сканирование кодовой базы показывает **противоречие между docs и кодом** — спросить, что считать SoT.
11. Запрашивается широкое **переименование/перемещение** модулей.
12. Изменение требует **drain очереди** или maintenance window.

В каждом из этих случаев:

1. Зафиксировать неопределённость в ответе.
2. Предложить 2–3 варианта с trade-off.
3. Не делать silent decision.
4. Не вносить destructive изменения до подтверждения.

---

## Финальное правило

> Если в какой-то момент непонятно, что делать — **уменьшай scope**, **читай docs**, **спрашивай**.
> Архитектура важнее скорости. Маленький честный diff лучше, чем большой «улучшенный».
