# 41. Implementation playbook

> **Статус:** обязательный операционный документ.
> **Аудитория:** разработчики, tech lead, AI-агенты (Cursor, Codex), DevOps-инженеры, архитекторы, контент-мигранты с WordPress.
> **Назначение:** дать единый, строгий, практический порядок реализации любых изменений в Symfony CMS Engine `zaborprofil`.
>
> Этот документ отвечает на вопрос: **«Как правильно и безопасно реализовывать изменения в этом проекте?»**
>
> При расхождении: [39-agent-guide](39-agent-guide.md) объясняет «почему», [40-cursor-rules](40-cursor-rules.md) определяет «как» для AI-агентов, а **этот документ** — **operational playbook** для всех типов изменений.

---

## Содержание

1. [Purpose of this playbook](#1-purpose-of-this-playbook)
2. [Core implementation philosophy](#2-core-implementation-philosophy)
3. [Standard workflow for any change](#3-standard-workflow-for-any-change)
4. [Task classification matrix](#4-task-classification-matrix)
5. [Change planning protocol](#5-change-planning-protocol)
6. [Layer selection guide](#6-layer-selection-guide)
7. [Playbook: adding a new public page](#7-playbook-adding-a-new-public-page)
8. [Playbook: adding a new admin feature](#8-playbook-adding-a-new-admin-feature)
9. [Playbook: adding a new API endpoint](#9-playbook-adding-a-new-api-endpoint)
10. [Playbook: changing application layer](#10-playbook-changing-application-layer)
11. [Playbook: changing domain logic](#11-playbook-changing-domain-logic)
12. [Playbook: changing Doctrine entities/repositories](#12-playbook-changing-doctrine-entitiesrepositories)
13. [Playbook: database schema change](#13-playbook-database-schema-change)
14. [Playbook: WordPress migration-related change](#14-playbook-wordpress-migration-related-change)
15. [Playbook: SEO/search/indexing change](#15-playbook-seosearchindexing-change)
16. [Playbook: Twig/frontend asset change](#16-playbook-twigfrontend-asset-change)
17. [Playbook: files/uploads change](#17-playbook-filesuploads-change)
18. [Playbook: configuration/environment change](#18-playbook-configurationenvironment-change)
19. [Playbook: cache/Redis change](#19-playbook-cacheredis-change)
20. [Playbook: Messenger/worker change](#20-playbook-messengerworker-change)
21. [Playbook: logging/observability change](#21-playbook-loggingobservability-change)
22. [Playbook: healthcheck change](#22-playbook-healthcheck-change)
23. [Playbook: error handling change](#23-playbook-error-handling-change)
24. [Playbook: Docker / Compose / local environment change](#24-playbook-docker--compose--local-environment-change)
25. [Playbook: Nginx / PHP-FPM / SSL change](#25-playbook-nginx--php-fpm--ssl-change)
26. [Playbook: install/deploy script change](#26-playbook-installdeploy-script-change)
27. [Playbook: CI/CD change](#27-playbook-cicd-change)
28. [What must be updated together](#28-what-must-be-updated-together)
29. [Common implementation anti-patterns](#29-common-implementation-anti-patterns)
30. [Common mistakes by AI agents](#30-common-mistakes-by-ai-agents)
31. [Checklists](#31-checklists)
32. [Examples of safe change scope](#32-examples-of-safe-change-scope)
33. [Definition of done](#33-definition-of-done)
34. [Quick reference](#34-quick-reference)

---

## 1. Purpose of this playbook

### 1.1 Зачем нужен этот документ

`zaborprofil` — это production CMS Engine, который заменяет действующий WordPress-сайт. У него три критичных свойства, которые нельзя игнорировать ни в одном изменении:

- **Это публичный SEO-сайт.** Любая ошибка в URL, canonical, sitemap, redirects, robots, JSON-LD — это потеря индексации, трафика и денег.
- **Это будущая платформа.** В кодовую базу будут наращиваться интернет-магазин, B2B/B2C-кабинеты, партнёрские модули. Архитектурные ошибки сейчас умножатся в кратном масштабе.
- **Это эксплуатируемая система.** Деплой идёт на VPS без Docker (native stack), миграции выполняются на живой БД, фоновые воркеры через systemd. «Чуть-чуть сломать» = починить вручную в 4 утра.

Этот playbook существует, чтобы:

- задать **единый стандартный порядок** реализации задач;
- защитить архитектурные границы (Clean Architecture + Modular Monolith);
- защитить SEO-контракты публичных URL;
- защитить миграции и данные production;
- защитить деплой, кэш, очереди, uploads;
- сделать работу AI-агента (Cursor) детерминированной и предсказуемой;
- сделать работу нового разработчика ускоренной и безопасной.

### 1.2 Для кого этот документ

| Аудитория | Что использует |
|-----------|----------------|
| Разработчик | Workflow, layer selection, playbooks, checklists |
| Tech lead / архитектор | Philosophy, layer rules, anti-patterns, definition of done |
| AI-агент (Cursor/Codex) | Standard workflow, task matrix, planning protocol, quick reference |
| DevOps-инженер | Configuration/env, deploy/install scripts, Nginx/PHP-FPM, healthcheck, CI/CD |
| Новичок в проекте | Workflow + classification matrix → нужный playbook |
| Контент-мигрант с WordPress | Раздел 14 + SEO-раздел 15 |
| Контролёр границ модулей | Layer selection guide + what-must-be-updated-together |

### 1.3 Какую проблему решает playbook

Без playbook любая задача превращается в:

- угадывание слоя;
- разбросанные изменения по всем зонам;
- забытые миграции и docs;
- сломанные публичные URL;
- silent изменения семантики кэша или ретраев;
- неполный PR, который проходит CI, но ломает прод.

Playbook делает работу **протоколируемой**: для каждого типа изменения определены вход, шаги, проверки, выход и обновляемые документы.

### 1.4 Почему CMS Engine нельзя менять хаотично

CMS Engine — это **product** для редактора и **service** для поисковика. Хаотичное изменение нарушает:

- **SEO-контракт** (URL, canonical, sitemap, redirects, structured data, hreflang).
- **Контент-контракт** (`Page`, `Block`, slug, статусы публикации).
- **Маршрутизацию** (Front/Admin/API/Dev зоны не должны пересекаться).
- **БД-инварианты** (миграции необратимы на живых данных).
- **Деплой-контракт** (release-based deploy, идемпотентные скрипты).
- **Кэш-семантику** (TTL, invalidation, ключи).
- **Безопасность** (Security firewall, voters, CSRF, uploads).

### 1.5 Связанные документы для контекста

- [00-overview](00-overview.md) — что это за проект.
- [01-product-purpose](01-product-purpose.md) — продуктовые цели и user journeys.
- [02-architecture](02-architecture.md) — высокоуровневая архитектура.
- [39-agent-guide](39-agent-guide.md) — mental model для AI-агента.
- [40-cursor-rules](40-cursor-rules.md) — строгие правила для Cursor.

---

## 2. Core implementation philosophy

16 принципов, которые применяются ко **всем** изменениям. Каждый принцип = что значит + почему важно + как применять + anti-pattern.

### 2.1 Architecture first

- **Что значит:** перед написанием первой строки кода — определить слой, модуль, контракт.
- **Почему:** Clean Architecture + Modular Monolith дают долгий жизненный цикл проекту. Нарушение границ оплачивается неделями рефакторинга.
- **Как:** прочитать [04-layer-rules](04-layer-rules.md) и [06-module-architecture](06-module-architecture.md) перед изменением. Ответить на вопрос: «к какому слою и модулю относится эта задача?».
- **Anti-pattern:** «сначала напишу контроллер, потом разнесём». Не разнесётся.

### 2.2 Minimal safe scope

- **Что значит:** изменение должно затрагивать минимум файлов, необходимых для решения задачи.
- **Почему:** широкие diff-ы трудно ревьюить, легко ломают несвязанные подсистемы.
- **Как:** перед коммитом запросить себя: «можно ли убрать половину diff и задача всё ещё решается?».
- **Anti-pattern:** «заодно отрефакторил соседний модуль, причесал имена, переписал три шаблона».

### 2.3 Explicit boundaries

- **Что значит:** Front/Admin/API/Dev зоны не пересекаются. Domain не зависит от Infrastructure. Module A не дёргает internal Module B.
- **Почему:** скрытые связи разрушают модульность, делают тесты невозможными.
- **Как:** общение между модулями — только через published Application contracts или domain events. Cross-area shared logic — в `src/Shared/`.
- **Anti-pattern:** контроллер `Front` инжектит `AdminPageRepository`. Domain entity использует `EntityManagerInterface`.

### 2.4 No unrelated changes

- **Что значит:** один PR / одна задача = одно изменение.
- **Почему:** смешанные diff невозможно ни ревьюить, ни откатывать.
- **Как:** косметика, форматирование, переименование — отдельным PR с заголовком `refactor:` / `chore:`.
- **Anti-pattern:** «починил баг + поменял шрифт + добавил два новых поля в admin».

### 2.5 Docs / tests / config evolve together

- **Что значит:** код, тесты, документация и конфиги обновляются в одном изменении.
- **Почему:** разрыв между ними = потерянная истина и сломанные онбоардинги.
- **Как:** для каждого крупного изменения свериться с разделом [28. What must be updated together](#28-what-must-be-updated-together).
- **Anti-pattern:** «доки потом», «тесты потом». Никакого «потом» не наступает.

### 2.6 Safe incremental change

- **Что значит:** большие задачи разбиваются на безопасные шаги, каждый шаг отдельно деплоится / откатывается.
- **Почему:** atomic commits = atomic rollbacks. Большие release rollback'ы — кошмар.
- **Как:** для DB schema — backward-compatible шаги (см. [13. DB schema change](#13-playbook-database-schema-change)).
- **Anti-pattern:** «миграция + удаление старого поля + переписывание трёх сервисов» в одном релизе.

### 2.7 Production mindset

- **Что значит:** каждое изменение оценивается через призму прода: что сломается на 1k req/min, при холодном старте, при потере Redis, при ошибке в миграции.
- **Почему:** dev-окружение прощает ошибки, прод — нет.
- **Как:** до merge ответить: что произойдёт при перезапуске PHP-FPM, при таймауте PostgreSQL, при empty cache?
- **Anti-pattern:** «у меня локально работает».

### 2.8 SEO-first thinking

- **Что значит:** любое изменение публичного URL, meta, canonical, sitemap, robots, JSON-LD рассматривается как **production-risk изменение**.
- **Почему:** потеря позиций в Яндекс/Google — прямой удар по бизнесу. URL'ы внешне закешированы у поисковиков.
- **Как:** см. раздел [15. SEO change](#15-playbook-seosearchindexing-change). Любая смена URL — обязательный 301 редирект.
- **Anti-pattern:** «переименовал slug у страницы, сайт же работает».

### 2.9 Rollback thinking

- **Что значит:** до merge известно, как откатить изменение.
- **Почему:** откатить нужно через 5 минут после релиза, не через 50.
- **Как:** для миграций — описать `down()` или явно отметить «forward-only + restore from backup». Для публичных URL — оставлять старый адрес работающим.
- **Anti-pattern:** «откатим — что-нибудь придумаем».

### 2.10 Migration thinking

- **Что значит:** любое изменение БД проектируется как миграция, а не как «правка локальной схемы».
- **Почему:** prod БД нельзя пересоздать. Любая ошибка в миграции = downtime + ручной recovery.
- **Как:** см. [18-migrations](18-migrations.md) и раздел [13](#13-playbook-database-schema-change). Тестировать на копии prod-данных.
- **Anti-pattern:** ручное `ALTER TABLE` в prod. `doctrine:schema:update --force` где угодно.

### 2.11 Deploy thinking

- **Что значит:** изменение совместимо с release-based deploy: миграции не требуют downtime, сервис не падает между шагами релиза.
- **Почему:** prod выкатывается без Docker, через bash-скрипты + systemd. Нет magic auto-recovery.
- **Как:** см. [34-deployment](34-deployment.md). Удалять старые поля/таблицы — только после ≥1 релиза без их использования.
- **Anti-pattern:** «релиз A удаляет колонку, а релиз A её и перестаёт читать в коде» — race condition между deploy steps.

### 2.12 Cache invalidation thinking

- **Что значит:** при добавлении кэша сразу проектируется invalidation strategy.
- **Почему:** stale data в SEO-кэшах = битые sitemap, неправильные canonical у тысяч страниц.
- **Как:** см. [23-cache-and-redis](23-cache-and-redis.md). Каждый cache pool имеет владельца и правила инвалидации.
- **Anti-pattern:** `$cache->get('foo', ...)` без TTL и без события на инвалидацию.

### 2.13 Observability by default

- **Что значит:** новые операции имеют логи, корреляционные id, метрики; ошибки маркируются уровнем и каналом.
- **Почему:** без observability диагностика prod невозможна.
- **Как:** см. [28-logging-observability](28-logging-observability.md). Логировать start / ok / fail для всех use cases и messenger handlers.
- **Anti-pattern:** silent `try/catch (\Throwable) {}`.

### 2.14 Security by default

- **Что значит:** access control, validation, CSRF и uploads-проверки добавляются в момент создания эндпоинта, а не «потом перед prod».
- **Почему:** пропущенный voter = leak admin-данных. Незавалидированный upload = RCE.
- **Как:** см. [20-security-and-access-control](20-security-and-access-control.md), [25-files-and-uploads](25-files-and-uploads.md).
- **Anti-pattern:** контроллер без `#[IsGranted]`, форма без CSRF token, upload без MIME/extension/size check.

### 2.15 Backward compatibility for public URLs

- **Что значит:** публичные URL — это контракт с поисковиком. Они не меняются молча.
- **Почему:** поисковик не знает о вашем рефакторинге.
- **Как:** старый URL → 301 → новый URL. Запись в системе redirects (см. [16-routing](16-routing.md), [26-seo-architecture](26-seo-architecture.md)).
- **Anti-pattern:** «переименовал slug, страница 404».

### 2.16 No hidden coupling between Front/Admin/API/Dev zones

- **Что значит:** контроллеры зон не наследуют друг от друга, не делят сервисы UI, не пересекают шаблоны.
- **Почему:** зона может иметь свой firewall, свой error handler, свой lifecycle. Связывание ломает security и UX.
- **Как:** см. [08-controller-architecture](08-controller-architecture.md). Общая логика — в Application layer.
- **Anti-pattern:** Admin контроллер использует Front partial, Dev controller рендерит admin-template.

---

## 3. Standard workflow for any change

Универсальная последовательность шагов. Применима к каждому изменению, от bugfix до нового модуля.

### 3.1 Шаги

1. **Понять задачу.** Сформулировать одной фразой, что должно стать возможным или перестать быть проблемой.
2. **Классифицировать изменение.** Найти строку в [task classification matrix](#4-task-classification-matrix).
3. **Прочитать связанную документацию.** Минимум: `00`, `02`, `04`, `39`, `40` + документы по типу задачи.
4. **Определить затрагиваемые слои.** Front / Admin / API / Dev / Application / Domain / Infrastructure.
5. **Составить impact map.** Какие модули, файлы, тесты, docs, конфиги, миграции, кэши — затрагиваются.
6. **Определить минимальный scope.** Что **точно** не входит в задачу.
7. **Определить риски.** SEO / БД / deploy / cache / security / uploads — что может сломаться.
8. **Реализовать изменение.** В правильных слоях, с минимальным diff.
9. **Обновить tests.** Unit / integration / functional — по [31-testing-strategy](31-testing-strategy.md).
10. **Обновить docs.** Внутри `docs/` + при необходимости ADR в `docs/adr/`.
11. **Обновить config/env templates.** `.env`, `.env.example`, `config/packages/*`, deploy templates.
12. **Проверить deploy impact.** Нужны ли новые env vars, шаги в release script, перезапуск worker'ов.
13. **Проверить rollback impact.** Можно ли откатить за 5 минут? Если нет — переразбить.
14. **Final review.** Запустить локальные gates ([AGENTS.md](../AGENTS.md) + [38-coding-standards](38-coding-standards.md) + CI ожидания).

### 3.2 Сводная таблица

| Step | Action | Output | Required docs | Common mistakes |
|------|--------|--------|---------------|-----------------|
| 1 | Понять задачу | Однострочное определение цели и acceptance | [01](01-product-purpose.md), [42](42-feature-development-guide.md) | Старт без формулировки задачи |
| 2 | Классифицировать | Тип задачи из matrix | [Раздел 4](#4-task-classification-matrix) | Не нашли тип → действуют наугад |
| 3 | Прочитать docs | Список прочитанных файлов | [40](40-cursor-rules.md) §2.1, [39](39-agent-guide.md) | Открыли только один файл |
| 4 | Определить слои | Список зон/слоёв | [04](04-layer-rules.md), [08](08-controller-architecture.md) | Перепутали Application и Domain |
| 5 | Impact map | Список файлов / docs / tests | [03](03-project-structure.md), [Раздел 28](#28-what-must-be-updated-together) | Забыли миграцию или sitemap |
| 6 | Scope | Список «не делаем» | [Раздел 32](#32-examples-of-safe-change-scope) | «Заодно отрефакторил» |
| 7 | Риски | Список рисков по категориям | [Раздел 5](#5-change-planning-protocol) | Игнор SEO/migration risk |
| 8 | Реализация | Минимальный diff | Соответствующий playbook 7–27 | Бизнес-логика в controller |
| 9 | Tests | Зелёные тесты | [31](31-testing-strategy.md) | Нет functional на новом endpoint |
| 10 | Docs | Обновлённые `docs/*` | [Раздел 28](#28-what-must-be-updated-together) | Placeholder docs |
| 11 | Config/env | `.env*`, `config/*`, deploy | [27](27-config-and-env.md) | Hardcoded secret |
| 12 | Deploy | Заметки в [34-deployment](34-deployment.md) | [34](34-deployment.md), [35](35-cicd.md) | Релиз требует ручных шагов |
| 13 | Rollback | План отката | [36](36-backup-restore.md), [37](37-runbooks.md) | «Откатим — придумаем» |
| 14 | Final review | Лог проверок | [38](38-coding-standards.md), [Раздел 33](#33-definition-of-done) | Пропущен `phpstan`/`phpunit` |

### 3.3 Связанные документы

- [03-project-structure](03-project-structure.md)
- [04-layer-rules](04-layer-rules.md)
- [31-testing-strategy](31-testing-strategy.md)
- [42-feature-development-guide](42-feature-development-guide.md)

---

## 4. Task classification matrix

Найди свой тип задачи. Это первая операция после прочтения тикета.

| Тип задачи | Зоны/слои | Ключевые риски | Обязательные проверки | Тесты | Docs |
|------------|-----------|----------------|------------------------|-------|------|
| **Small bugfix** | Точечный слой | Регрессия | Reproduce → fix → test | Regression unit/functional | Changelog при необходимости |
| **Frontend / Twig UI change** | Presentation (Twig) | Сломанная разметка, SEO-структура | Lint:twig, responsive, SEO heading hierarchy | Smoke functional | [21](21-templates-and-twig.md), [22](22-frontend-assets.md) |
| **Vue / admin SPA change** | `assets/admin`, API | Расхождение API ↔ SPA | `npm run build`, e2e admin | Vue unit + admin functional | [22](22-frontend-assets.md), [12](12-admin-area.md) |
| **Public page change** | Front controller + Twig + Application | SEO, URL, sitemap | URL collision, canonical, sitemap | Functional + SEO snapshot | [13](13-front-area.md), [16](16-routing.md), [26](26-seo-architecture.md) |
| **Admin feature** | Admin controller + Application + Domain | RBAC, CSRF, audit | Voter, форма, flash, redirect after POST | Functional admin | [12](12-admin-area.md), [19](19-forms-dto-validation.md), [20](20-security-and-access-control.md) |
| **API change** | API controller + Application | Контракт, версионирование | DTO contracts, error format, auth | Functional API | [14](14-api-area.md), [30](30-error-handling.md) |
| **Business / use-case change** | Application | Семантика операции | Транзакционность, идемпотентность | Unit handler + integration | [09](09-application-layer.md) |
| **Domain model change** | Domain | Инварианты, целостность данных | Invariants, value objects | Unit domain | [05](05-domain-model.md), [10](10-domain-layer.md) |
| **Doctrine entity change** | Domain + Infrastructure | Маппинг, миграция | Migration diff, проверка маппинга | Integration repository | [17](17-doctrine-and-database.md), [18](18-migrations.md) |
| **Repository / query change** | Infrastructure | N+1, индексы, performance | EXPLAIN, fixtures | Integration repository | [17](17-doctrine-and-database.md) |
| **Database schema change** | Infrastructure + Domain | Downtime, потеря данных | Backward-compatible шаги, backfill, индексы | Integration + migration test | [18](18-migrations.md), [13. Раздел](#13-playbook-database-schema-change) |
| **WordPress migration** | Infrastructure (importer) + SEO | URL-сохранение, потеря индексации | Mapping table, redirects, canonical | Integration importer | [Раздел 14](#14-playbook-wordpress-migration-related-change) |
| **SEO / meta / sitemap change** | Front + Application + Twig | Индексация, ranking | URL, canonical, sitemap, robots, JSON-LD | Functional SEO | [26](26-seo-architecture.md), [SEO_GUIDE.md](legacy/SEO_GUIDE.md) |
| **Route / URL change** | Front/API + Application + redirects | 301/410, sitemap | Конфликт маршрутов, sitemap, redirects | Functional + redirect test | [16](16-routing.md), [REDIRECTS.md](legacy/REDIRECTS.md) |
| **Files / uploads change** | Application + Infrastructure (Storage) | Path traversal, RCE, leak | MIME/ext/size, permissions, public/private | Integration + functional | [25](25-files-and-uploads.md), [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md) |
| **Cache / Redis change** | Infrastructure | Stale data, hot keys | TTL, invalidation, key naming | Integration (cache hit/miss) | [23](23-cache-and-redis.md) |
| **Messenger / worker change** | Application + Infrastructure + ops | Дубли, бесконечные ретраи | Idempotency, retry policy, graceful shutdown | Functional in-memory transport | [24](24-messenger-and-queues.md), [37](37-runbooks.md) |
| **Deploy / config change** | DevOps | Сломанный релиз | Idempotency, dry-run, staging | Manual on staging | [34](34-deployment.md), [DEPLOY.md](legacy/DEPLOY.md) |
| **Docker / local-dev change** | DevOps (local) | Сломанный onboarding | `make build && make up` чистый | Manual | [32](32-docker-architecture.md), [33](33-local-development.md) |
| **systemd / Nginx / PHP-FPM change** | DevOps (prod) | Downtime, 502, регресс конфигов | Reload/test, runbook | Manual on staging | [34](34-deployment.md), [37](37-runbooks.md) |
| **Observability / logging change** | Cross-cut | Шумные логи, leak секретов | Уровни, каналы, redaction | Unit logger | [28](28-logging-observability.md), [LOGGING.md](legacy/LOGGING.md) |
| **Healthcheck change** | Infrastructure + ops | Сломанный rollout | Не тяжелее 200ms, readiness vs liveness | Functional /health | [29](29-healthchecks.md) |
| **Security hardening** | Cross-cut | Регрессия пользовательского UX | Voters, headers, CSRF, rate limit | Functional security | [20](20-security-and-access-control.md), [SECURITY.md](legacy/SECURITY.md) |
| **Refactor** | Точечный слой | Изменение поведения | Тесты до = тесты после | Все существующие | [38](38-coding-standards.md) |

> Если тип задачи не находится в матрице — сначала декомпозируй её на типы из матрицы.

---

## 5. Change planning protocol

**До** написания кода каждое изменение проходит planning protocol. AI-агент **обязан** оформить planning note (см. шаблон ниже) и отдать пользователю/ревьюеру до старта реализации, если задача нетривиальна (всё, что выходит за «small bugfix»).

### 5.1 Что определить до реализации

| Что определить | Как формулировать |
|----------------|--------------------|
| Цель | 1 предложение, business outcome |
| Вход | DTO / параметр / событие |
| Выход | DTO / response / state change |
| Impacted files | Список путей |
| Impacted docs | Список `docs/*.md` |
| Non-impacted layers | Что точно не трогаем |
| Возможные побочные эффекты | Cache, queue, mailer, search index |
| Нужна ли миграция | yes/no + reasoning |
| Изменения в deploy | yes/no + что |
| Изменения в env/config | yes/no + переменные |
| Изменения в Redis/cache | yes/no + ключи и TTL |
| Изменения в frontend assets | yes/no + Vite chunk |
| Изменения в uploads/files | yes/no + path |
| Изменения в Nginx/PHP-FPM | yes/no + какой блок |
| Docs/tests/logging updates | список |
| SEO risk | yes/no + что именно |
| Public URL risk | yes/no + какие URL |
| Backward compatibility risk | yes/no + кого ломает |

### 5.2 Шаблон planning note

```markdown
# Planning note: <короткое название>

## Goal
<1-2 предложения. Business outcome.>

## User-facing impact
<Что меняется для visitor / editor / admin / API consumer.>

## Technical impact
<Какие модули, слои, контракты затрагиваются.>

## Affected layers
- [ ] Front
- [ ] Admin
- [ ] API
- [ ] Dev
- [ ] Application
- [ ] Domain
- [ ] Infrastructure
- [ ] Persistence (Doctrine/DB)
- [ ] Presentation (Twig/Vue/Tailwind)
- [ ] Files/Uploads
- [ ] Cache/Redis
- [ ] Queue/Worker
- [ ] Deploy
- [ ] Docs
- [ ] Tests

## Affected files
- src/...
- templates/...
- migrations/...
- config/...
- assets/...
- docs/...

## Affected docs
- docs/<file>.md — что обновится

## Non-goals
- Что **не** делаем в этой задаче

## Risks
- SEO: ...
- DB: ...
- Cache: ...
- Security: ...
- Uploads: ...
- Deploy: ...
- Backward compatibility: ...

## DB migration needed?
yes/no — описание шагов и backfill plan

## Config / env changes needed?
yes/no — список переменных + defaults

## Cache invalidation needed?
yes/no — какие ключи / какие pools

## Files/uploads impact
yes/no — какие пути, новые валидации

## SEO impact
yes/no — URL, canonical, sitemap, robots, JSON-LD

## Deploy impact
yes/no — новые шаги в release script, рестарт worker'ов

## Rollback plan
- Шаги отката за < 5 минут.

## Tests to add/update
- Unit: ...
- Integration: ...
- Functional: ...

## Documentation to update
- docs/<file>.md — что добавить
```

### 5.3 Когда planning note **обязателен**

- Любое изменение БД-схемы.
- Любое изменение публичного URL / canonical / sitemap / robots / JSON-LD.
- Любое изменение деплой-скрипта или Nginx/PHP-FPM конфига.
- Новый модуль или новая интеграция.
- Любое изменение Messenger transport / retry policy.
- Любое изменение security firewall / voter / RBAC.
- Любое изменение upload-логики.

---

## 6. Layer selection guide

Самый частый источник ошибок — попадание логики в неправильный слой. Используй decision tree ниже.

### 6.1 Decision tree (где разместить изменение)

```
Это изменение публичного URL/HTML/SEO?
├── Да → Front controller + Application + Twig + (SEO docs)
└── Нет
    │
    Это изменение admin UX/CRUD?
    ├── Да → Admin controller + Application + (FormType/DTO) + (Voter)
    └── Нет
        │
        Это изменение API контракта?
        ├── Да → API controller + Application + DTO
        └── Нет
            │
            Это бизнес-операция?
            ├── Да → Application use case + Domain
            └── Нет
                │
                Это правило/инвариант домена?
                ├── Да → Domain (Entity / VO / DomainService)
                └── Нет
                    │
                    Это техническая интеграция (DB / Redis / mailer / storage)?
                    ├── Да → Infrastructure
                    └── Нет → Shared / cross-cut (DI, Twig extension, Console)
```

### 6.2 Когда что менять

#### When to change Front controllers (`src/...UI/Front/...` или `src/Module/.../UI/Front/`)

- **Признак правильного слоя:** изменение касается публичного HTML-ответа, breadcrumbs, рендеринга страницы.
- **Признак неправильного:** в контроллере появляется бизнес-логика, прямой доступ к `EntityManager`, формирование сложных мета-данных «руками».
- **Anti-pattern:** `$em->getRepository(Page::class)->findBy(...)` прямо в контроллере. Контроллер обязан получать готовый view-model из Application layer.

#### When to change Admin controllers (`src/Module/.../UI/Admin/...`)

- **Признак правильного:** меняется admin CRUD/action, форма, listing, batch-операция.
- **Признак неправильного:** контроллер принимает решение о бизнес-инвариантах, рассылает email, инвалидирует кэши.
- **Anti-pattern:** `$page->setStatus('published')` в контроллере. Должен быть use case `PublishPageHandler`.

#### When to change API controllers (`src/Module/.../UI/Api/...`)

- **Признак правильного:** меняется HTTP-контракт, формат ошибки, версия эндпоинта.
- **Признак неправильного:** контроллер делает работу с Doctrine entity напрямую и сериализует её через `serializer` без выделенного DTO.
- **Anti-pattern:** возвращать Doctrine entity как JSON — это утечка модели наружу.

#### When to change Dev controllers (`src/Module/.../UI/Dev/...`)

- **Признак правильного:** dev-инструмент, симулятор, debug-страница; видна **только** в `dev` env.
- **Признак неправильного:** dev-controller вызывает production-data writes, попадает в маршруты prod.
- **Anti-pattern:** dev-route без `condition: "%kernel.debug% == true"` или без env-guard.

#### When to change application services / use cases

- **Признак правильного:** появляется/меняется бизнес-операция (один глагол + объект): `PublishPage`, `ImportProduct`, `RegisterCustomer`.
- **Признак неправильного:** use case знает про HTTP, Twig, FormType.
- **Anti-pattern:** `PublishPageHandler` принимает `Request $request`. Должен принимать `PublishPageCommand` (DTO).

#### When to change domain model

- **Признак правильного:** появляется/меняется правило/инвариант: «нельзя опубликовать страницу без `slug`», «цена не может быть отрицательной».
- **Признак неправильного:** domain entity использует `EntityManager`, Symfony container, `HttpClient`.
- **Anti-pattern:** anemic entity (геттеры/сеттеры без методов поведения). Domain god-object с 50 методами.

#### When to change Doctrine entities

- **Признак правильного:** добавляется/меняется поле, маппинг, индекс, связь.
- **Признак неправильного:** меняется entity без миграции и без обновления `repository`/`tests`.
- **Anti-pattern:** изменение типа поля без `up()`/`down()` миграции.

#### When to change repositories

- **Признак правильного:** новый query method, оптимизация запроса, добавление кастомной выборки.
- **Признак неправильного:** repository возвращает «магические массивы» вместо entity или DTO.
- **Anti-pattern:** repository, делающий side-effects (рассылающий events, дёргающий внешние сервисы).

#### When to change Twig templates

- **Признак правильного:** меняется визуальная структура, partial, layout, мета-блок.
- **Признак неправильного:** в шаблоне есть `{% set products = ... %}` с обращением к Doctrine, бизнес-расчёты, conditional flow для бизнес-правил.
- **Anti-pattern:** `{% if user.purchases|filter(...) %}` со сложной логикой. Должен быть готовый view model.

#### When to change Vue / admin SPA

- **Признак правильного:** меняется admin SPA UX или его связка с admin API.
- **Признак неправильного:** SPA вызывает прямые SQL-эндпоинты или dev-only routes.
- **Anti-pattern:** SPA знает про внутренние Doctrine ID без UUID/slug, ломается при любом рефакторинге БД.

#### When to change frontend assets (`assets/site`, `assets/admin`)

- **Признак правильного:** меняется CSS/JS/Tailwind конфиг, Vite chunk.
- **Признак неправильного:** меняется только asset, но не Twig — и наоборот.
- **Anti-pattern:** Tailwind-классы без `npm run build` после правки `tailwind.config.js`.

#### When to change forms / DTO / validators

- **Признак правильного:** новые поля ввода, новые правила валидации, новый use case input.
- **Признак неправильного:** валидация в контроллере: `if ($request->get('email') !== ...)`.
- **Anti-pattern:** DTO, который принимает Doctrine entity вместо примитивов.

#### When to change infrastructure services

- **Признак правильного:** меняется адаптер БД/Redis/mailer/storage/HTTP клиент.
- **Признак неправильного:** инфраструктурный сервис принимает решения о business rules.
- **Anti-pattern:** `MailerService` решает, имеет ли пользователь право получить письмо.

#### When to change files/uploads logic

- **Признак правильного:** меняются правила хранения, валидации, доступа к файлам.
- **Признак неправильного:** загрузка файлов из контроллера без проверки MIME/extension/size/owner.
- **Anti-pattern:** хранить uploaded файлы в `public_html/` без проверки и санитарного имени.

#### When to change Messenger handlers / workers

- **Признак правильного:** новая фоновая операция, новый retry policy, новая очередь.
- **Признак неправильного:** handler делает HTTP I/O без таймаутов и retry, не идемпотентен.
- **Anti-pattern:** handler без логирования start/ok/fail, без явной retry стратегии.

#### When to change cache logic

- **Признак правильного:** новый pool, новый ключ, новая инвалидация.
- **Признак неправильного:** кэшируется user-specific data в shared pool.
- **Anti-pattern:** `cache.app` для всего; ключи без префикса модуля.

#### When to change deploy / install scripts

- **Признак правильного:** меняется release procedure, добавляется новый шаг (миграция, seeding, asset rebuild).
- **Признак неправильного:** скрипт не идемпотентен, не дружит с retry.
- **Anti-pattern:** `cp -rf /tmp/app/* /var/www/`, `rm -rf` без проверки переменной.

#### When to change Nginx / PHP-FPM

- **Признак правильного:** меняются location-блоки, security headers, gzip/brotli, FPM pool.
- **Признак неправильного:** Nginx делает бизнес-роутинг (а не роутинг по статике/Symfony front).
- **Anti-pattern:** Nginx rewrites дублируют Symfony routes.

#### When to change docs

- **Всегда**, когда меняется поведение, контракт, конфигурация, deploy. Документация — часть кода.

#### When to change tests

- **Всегда**, когда меняется поведение. Без тестов изменение не считается завершённым.

### 6.3 Anti-patterns по выбору слоя

- Domain зависит от Infrastructure.
- Application принимает `Request`/`Response`/`SessionInterface`.
- Controller дёргает `EntityManager` напрямую.
- Twig делает `entity.repository.findBy(...)`.
- Front-controller использует Admin-сервис (или наоборот).
- Dev-route доступен в prod.
- API возвращает Doctrine entity напрямую.
- Module A напрямую инстанцирует internal класс Module B.

### 6.4 Связанные документы

- [04-layer-rules](04-layer-rules.md)
- [06-module-architecture](06-module-architecture.md)
- [08-controller-architecture](08-controller-architecture.md)
- [09-application-layer](09-application-layer.md)
- [10-domain-layer](10-domain-layer.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
- [19-forms-dto-validation](19-forms-dto-validation.md)

---

## 7. Playbook: adding a new public page

### 7.1 Шаги

1. **Определить назначение страницы.** Маркетинговая, продуктовая, услуга, поддержка, юридическая. От этого зависит layout и SEO intent.
2. **Определить SEO intent.** Под какой кластер запросов; будет ли страница в sitemap; какой canonical; нужны ли hreflang.
3. **Определить URL.** Slug согласован с SEO, не конфликтует с existing routes (см. [16-routing](16-routing.md)).
4. **Проверить конфликт маршрутов.** `bin/console debug:router | grep <slug>`. Проверить redirects на этот URL.
5. **Решить: контентная страница или код-страница.**
   - Контентная (управляется редактором) — создаётся через admin (`Page` entity с блоками). Кода писать не надо. См. P1 в [Разделе 32](#32-examples-of-safe-change-scope).
   - Код-страница (имеет логику, не CMS-контент) — создаём Front-контроллер.
6. **Создать / обновить Front controller.** В `src/Module/<X>/UI/Front/<Name>Controller.php`. Тонкий контроллер: получает DTO/view-model из Application layer.
7. **Создать application service / use case** — если нужен сбор/расчёт данных (например, listing услуг). DTO `…View` возвращается в контроллер.
8. **Создать Twig template** в `templates/front/<area>/<page>.html.twig`. Расширяет общий layout, использует partial-блоки. Бизнес-логику в шаблон не пускать.
9. **Добавить breadcrumbs** через общий механизм (Twig partial / view model property `breadcrumbs`).
10. **Добавить meta title / description / canonical / OG / robots** через единый SEO-блок (см. [26-seo-architecture](26-seo-architecture.md)). В `Page` уже есть `title`, `h1`, `indexable`, `metaDescription`, `canonicalUrl`, OpenGraph-поля и `jsonLd`; новые публичные страницы должны переиспользовать этот контракт и покрываться functional-тестами.
11. **Добавить sitemap impact.**
    - Если страница динамическая (`Page` entity) — она автоматически попадает в sitemap (см. [SEO_GUIDE.md](legacy/SEO_GUIDE.md)).
    - Если код-страница — добавить в `SitemapController` или новый `SitemapSourceProviderInterface`.
12. **Добавить tests.** Functional: статус 200, наличие h1, canonical, мета-теги.
13. **Обновить docs.** [13-front-area](13-front-area.md), [16-routing](16-routing.md), [SEO_GUIDE.md](legacy/SEO_GUIDE.md).

### 7.2 Что делать нельзя

- ❌ Бизнес-логика в контроллере или Twig.
- ❌ Прямой доступ к `EntityManager` из Twig или контроллера.
- ❌ SEO-теги inline с magic-строками; должны браться из `Page`/view-model.
- ❌ Создать страницу, забыв про sitemap / canonical / 404 для соседних slug-ов.
- ❌ Дублирование маршрута, конфликт с Admin/API/Dev зонами.

### 7.3 Типичные ошибки

- Забыли проверить `debug:router` → роут перекрыл существующий.
- Забыли canonical → дубль контента в индексе.
- Забыли functional test → регрессия дойдёт до прода.
- Назвали slug по-английски при русскоязычной структуре сайта (или наоборот) — потеряли консистентность URL.

### 7.4 Связанные документы

- [13-front-area](13-front-area.md)
- [16-routing](16-routing.md)
- [21-templates-and-twig](21-templates-and-twig.md)
- [26-seo-architecture](26-seo-architecture.md)

---

## 8. Playbook: adding a new admin feature

### 8.1 Шаги

1. **Определить admin use case.** Один глагол + один объект: `PublishPage`, `ArchiveLead`, `RegenerateSitemap`.
2. **Определить права доступа.** Какая роль/voter. Если нужен новый — добавить voter.
3. **Решить: CRUD или custom action.** CRUD — стандартный CRUD endpoint; custom action — отдельный handler + endpoint.
4. **Создать Admin controller** (`src/Module/<X>/UI/Admin/...`). Тонкий: получает DTO, вызывает handler, отдаёт ответ.
5. **Создать DTO + Validator** в `Application/DTO/` (см. [19-forms-dto-validation](19-forms-dto-validation.md)). Для классических HTML-форм — FormType, для admin SPA / API — DTO + Validator.
6. **Создать Application service / use case** в `Application/Command/` + `Application/Handler/`. Прямой `EntityManager` — только в Application или Infrastructure, никогда в контроллере.
7. **Обновить Twig templates админки** или Vue-компонент SPA, в зависимости от того, где живёт UI.
8. **Проверить CSRF / security.**
   - CSRF token у HTML-форм (Symfony FormType добавляет автоматически).
   - `#[IsGranted(...)]` или voter check.
   - Audit log для опасных действий.
9. **Flash / error handling.** После POST — redirect (PRG pattern), flash сообщение пользователю. Ошибки — через единый error handler ([30-error-handling](30-error-handling.md)).
10. **Добавить tests.** Functional admin (логин под admin → action → проверка БД-состояния и UI).
11. **Обновить docs.** [12-admin-area](12-admin-area.md), [ADMIN_FRONTEND.md](legacy/ADMIN_FRONTEND.md), [ADMIN_GUIDE.md](legacy/ADMIN_GUIDE.md), [ROLES.md](legacy/ROLES.md).

### 8.2 Что делать нельзя

- ❌ Бизнес-инварианты (`if ($page->isPublished()) { ... }`) в Admin controller. Это работа Domain/Application.
- ❌ Прямой `$em->flush()` из контроллера для бизнес-операций.
- ❌ Action без `#[IsGranted]` / voter.
- ❌ Form без CSRF.
- ❌ HTML-ответ на POST вместо redirect-after-POST.
- ❌ Опасное действие (delete / restore / republish all) без подтверждения и audit log.

### 8.3 Типичные ошибки

- Забыли voter / `#[IsGranted]` — admin-action доступен по URL без авторизации.
- DTO принимает Doctrine entity вместо примитивов.
- Кэш не инвалидируется после admin-write.
- Flash-сообщение перетирает прежнее.

### 8.4 Связанные документы

- [12-admin-area](12-admin-area.md)
- [19-forms-dto-validation](19-forms-dto-validation.md)
- [20-security-and-access-control](20-security-and-access-control.md)
- [21-templates-and-twig](21-templates-and-twig.md)
- [22-frontend-assets](22-frontend-assets.md)

---

## 9. Playbook: adding a new API endpoint

### 9.1 Шаги

1. **Определить контракт API.** Метод, путь, request schema, response schema, ошибки.
2. **Определить request DTO** в `Application/DTO/Input/`. Только примитивы / VO. Не принимать Doctrine entity.
3. **Определить response DTO** в `Application/DTO/Output/`. Не сериализовывать Doctrine entity напрямую.
4. **Определить validation.** Symfony Validator constraints на DTO + бизнес-валидация в use case.
5. **Определить auth / access control.** API-token / JWT / IP-allowlist для admin API. Public API — rate-limited.
6. **Создать API controller** (`src/Module/<X>/UI/Api/...`). Контроллер → handler → response DTO.
7. **Создать use case** в Application layer. Один handler — одна операция.
8. **Обработать ошибки в стабильном формате** ([30-error-handling](30-error-handling.md)). Единый JSON-формат `{ error: { code, message, details } }`.
9. **Добавить tests.** Functional: успех, валидация, auth, error format.
10. **Обновить API docs.** [14-api-area](14-api-area.md), OpenAPI/Swagger при наличии.

### 9.2 Versioning

- Public API живёт под `/api/v1/...`. Несовместимое изменение — `/v2`. Старая версия не удаляется без deprecation period.
- Admin API — под `/admin/api/...`. Версионирование менее строгое, но **breaking change** требует обновления admin SPA в одной задаче.
- Breaking change без bump версии **запрещён**.

### 9.3 Backward compatibility

- Удалять поля/эндпоинты — только через `Deprecation` header + журналирование использования.
- Добавлять поля в response — безопасно, если клиенты толерантны (нужно подтверждение).

### 9.4 Error format

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Invalid input",
    "details": [
      { "field": "email", "code": "invalid_format" }
    ]
  }
}
```

- HTTP status соответствует семантике (`400`, `401`, `403`, `404`, `409`, `422`, `429`, `500`).
- Никогда не отдавать stack trace в response.

### 9.5 Security

- `#[IsGranted]` / voter / firewall-isolation.
- Rate limiting (Symfony RateLimiter) для public API.
- Логировать неудачные auth-запросы (rate-limited, чтобы не залить лог).

### 9.6 Common mistakes

- Возврат Doctrine entity напрямую → утечка модели.
- Stack trace в response.
- Отсутствие versioning → breaking change ломает клиентов.
- Отсутствие rate limiting → API может стать DDoS-вектором.

### 9.7 Связанные документы

- [14-api-area](14-api-area.md)
- [19-forms-dto-validation](19-forms-dto-validation.md)
- [20-security-and-access-control](20-security-and-access-control.md)
- [30-error-handling](30-error-handling.md)

---

## 10. Playbook: changing application layer

### 10.1 Когда задача относится к Application

- Появляется/меняется бизнес-операция, которая координирует Domain + Infrastructure.
- Появляется/меняется DTO input/output use case'а.
- Меняется транзакционная граница операции.

### 10.2 Как проектировать use case

- Один handler — одна операция. Имя = глагол + объект (`PublishPage`, `ImportProduct`).
- Вход — `Command` или `Query` DTO. Никаких `Request`/`Response`.
- Выход — DTO или void (для команд).
- Транзакционная граница — внутри handler, не в контроллере.
- Side effects (events, queue, mail) — в handler через интерфейсы.

### 10.3 Command / Query разделение

- **Command** — изменяет состояние, обычно возвращает void / id.
- **Query** — читает состояние, возвращает DTO/коллекцию.
- Не смешивать (`UpdateAndGetPageCommand` — anti-pattern).

### 10.4 Как **не смешивать** Application с другими слоями

- ❌ Application принимает `Request` / `Response` / `Session`.
- ❌ Application использует Doctrine `QueryBuilder`/`createQuery` напрямую (это Infrastructure).
- ❌ Application содержит business invariant — это работа Domain.
- ❌ Handler делает HTTP I/O без интерфейса (должен быть `HttpClientInterface`-обёртка).

### 10.5 Как тестировать use cases

- Unit handler: заглушки на repositories/services, проверка вызовов и состояния.
- Integration: реальный repository против тестовой БД.
- Functional: HTTP-вызов до handler через controller.

### 10.6 Как документировать use case contracts

- В коде: PHPDoc на handler с описанием side effects, throws.
- В docs: краткое описание в [09-application-layer](09-application-layer.md) или README модуля.

### 10.7 Связанные документы

- [09-application-layer](09-application-layer.md)
- [04-layer-rules](04-layer-rules.md)
- [31-testing-strategy](31-testing-strategy.md)

---

## 11. Playbook: changing domain logic

### 11.1 Когда задача относится к Domain

- Меняется правило/инвариант («нельзя опубликовать без slug», «цена ≥ 0»).
- Появляется новый Value Object, новый Domain Service, новый Aggregate.
- Меняется жизненный цикл entity (статусы, переходы).

### 11.2 Как менять entities / VO / domain services

- Все инварианты — в методах поведения entity / VO. Сеттеры допустимы только тривиальные.
- Value Object — `final readonly`, immutable, equality по value.
- Domain Service — для логики, не принадлежащей одному агрегату.
- Не использовать Symfony container, `EntityManager`, `HttpClient` в domain.

### 11.3 Как сохранить invariants

- Конструктор entity не должен оставлять её в невалидном состоянии.
- Любая мутация проходит через метод поведения (`page.publish()`, `order.cancel()`).
- Невалидный переход → бросать `DomainException`-наследника.

### 11.4 Как тестировать

- Unit-тесты domain-логики — без Symfony, без БД.
- Покрывать: happy path, нарушение инвариантов, граничные значения.

### 11.5 Как документировать business rules

- В коде: PHPDoc + понятные имена методов.
- В docs: [05-domain-model](05-domain-model.md) — business rules глазами продукта.
- Сложные жизненные циклы — диаграмма состояний в [05](05-domain-model.md).

### 11.6 Anti-patterns

- **Anemic domain:** entity = bag of getters/setters, вся логика в сервисах.
- **Domain chaos:** entity знает про HTTP, кэши, mailer.
- **Bypass invariants:** `setStatus($string)` вместо `publish()`.
- **God aggregate:** один entity на всю систему.

### 11.7 Связанные документы

- [05-domain-model](05-domain-model.md)
- [10-domain-layer](10-domain-layer.md)
- [04-layer-rules](04-layer-rules.md)
- [17-doctrine-and-database](17-doctrine-and-database.md)

---

## 12. Playbook: changing Doctrine entities/repositories

### 12.1 Когда менять entity

- Добавляется/меняется поле, индекс, связь.
- Меняется маппинг (тип колонки, nullable, default, FK).

### 12.2 Когда менять repository

- Появляется/оптимизируется выборка.
- Появляется кастомный SQL/DBAL запрос.
- Нужен criteria-based query method.

### 12.3 Когда добавлять query method

- Когда query вызывается в нескольких местах либо имеет нетривиальные условия.
- Однократный fetch по id — `find($id)` достаточен, без обёртки.

### 12.4 Когда нужен custom DBAL query

- Сложный отчёт, агрегации, оконные функции.
- Производительный bulk-update без hydration.

### 12.5 Lazy / eager loading

- По умолчанию — lazy. Eager — точечно (`fetch="EAGER"`) или через `addSelect` в query.
- Не злоупотреблять `fetch="EAGER"` на коллекциях — потеряете контроль.

### 12.6 Индексы

- При добавлении нового поля для фильтра/сортировки — добавлять индекс в той же миграции.
- Уникальные ограничения — на уровне БД (`UNIQUE`), не только в коде.
- Композитные индексы — по реальному порядку колонок в WHERE.

### 12.7 Performance

- N+1 — через `addSelect` или `JOIN FETCH`.
- Тяжёлые операции — без hydration (`getArrayResult()` или DBAL).
- Большие выборки — `iterate` или batch + `clear()`.

### 12.8 Тестирование repository

- Integration test против тестовой БД (см. [31-testing-strategy](31-testing-strategy.md)).
- Покрытие: основной query path + граничные случаи (empty, NULL, ordering).

### 12.9 Связанные документы

- [17-doctrine-and-database](17-doctrine-and-database.md)
- [18-migrations](18-migrations.md)

---

## 13. Playbook: database schema change

> **Warning.** Любое изменение схемы — production-risk. Никаких `doctrine:schema:update --force` где бы то ни было, никаких ручных `ALTER TABLE` на проде. Только Doctrine Migrations.

### 13.1 Как понять, что нужна миграция

- Изменился маппинг entity (поле, тип, nullable, индекс, FK, имя таблицы/колонки).
- Появилась новая entity.
- Изменилось имя таблицы / колонки.

### 13.2 Как спроектировать change safely

Все изменения схемы делятся на:

- **Additive (безопасные):** добавление nullable-колонки, нового индекса, новой таблицы.
- **Mutating (опасные):** изменение типа/имени, NOT NULL без default, удаление колонки/таблицы, изменение FK.

Mutating-изменения **разбиваются** на серию additive-шагов:

1. Добавить новую колонку (nullable / default).
2. Backfill данных (отдельной миграцией или batch-командой).
3. Сделать NOT NULL / переключить код на новую колонку.
4. Удалить старую колонку (через ≥1 release без её использования).

### 13.3 Как писать Doctrine migration

- Запуск: `make migration` или `bin/console doctrine:migrations:diff`.
- Файл миграции **читается глазами** перед коммитом — Doctrine может предложить лишнее.
- Для PostgreSQL использовать ровно те типы, что в entity (`json` vs `jsonb`, `timestamp` vs `timestamptz`).
- Не использовать `doctrine:schema:update --force`.

### 13.4 Backfill

- Backfill больших таблиц — батчами (`LIMIT 1000`), не одним `UPDATE`.
- Backfill в CLI-команде (Symfony Console), а не в migration, если дольше пары секунд.
- В миграции допускается короткий backfill для таблиц малого размера.

### 13.5 Индексы

- Создавать `CONCURRENTLY` для больших таблиц, чтобы не блокировать (PostgreSQL).
- Уникальные ограничения проверять на дубли **до** применения (отдельная query в проверке pre-deploy).

### 13.6 Foreign keys

- Добавлять с `ON DELETE` стратегией явно (`CASCADE`, `RESTRICT`, `SET NULL`).
- Не добавлять FK на «грязных» данных без cleanup.

### 13.7 Rollback

- Каждая миграция имеет `down()` — он либо реальный, либо явно `throw new \LogicException('Forward-only migration; restore from backup')`.
- Forward-only миграции (drop колонки) — тестируются на staging + бэкап перед деплоем.

### 13.8 Update entities/repositories/services/tests/docs

- При изменении поля обновить:
  - Entity маппинг.
  - Repository (если поле в queries).
  - Use case / handler (если поле в операциях).
  - DTO (input/output).
  - Twig / Vue (если поле в UI).
  - Tests.
  - Docs ([05-domain-model](05-domain-model.md), [17-doctrine-and-database](17-doctrine-and-database.md)).

### 13.9 Deploy order

- Release-based deploy ([34-deployment](34-deployment.md)) выполняет:
  1. Бэкап БД.
  2. `doctrine:migrations:migrate --no-interaction`.
  3. Перезапуск PHP-FPM / worker'ов.
- Миграции — **до** обновления PHP-кода для additive, **после** — для drop. Либо использовать backward-compatible миграции, тогда порядок не критичен.

### 13.10 Backward compatibility

- На время deploy одновременно работают **старый** и **новый** код. Миграция должна быть совместима с обоими (если не реализован подход stop-the-world).
- Отсюда правило: **никогда** удалять колонку в той же миграции/релизе, в котором перестают её использовать.

### 13.11 Тестирование локально

- Применить миграцию: `make migrate` / `bin/console doctrine:migrations:migrate`.
- Откатить: `bin/console doctrine:migrations:migrate prev`.
- Применить заново: проверка идемпотентности.
- Тестировать на копии prod (staging).

### 13.12 Migration checklist

- [ ] Маппинг entity и миграция совпадают.
- [ ] Миграция читается глазами, без лишних DROP/RENAME.
- [ ] Indexes покрывают новые WHERE/ORDER.
- [ ] FK имеют явную ON DELETE стратегию.
- [ ] NOT NULL без default — только на пустой таблице или после backfill.
- [ ] `down()` либо реализован, либо явно forward-only с комментарием.
- [ ] Локальный прогон up → down → up успешен.
- [ ] Бэкап стратегии существует ([36](36-backup-restore.md)).
- [ ] Backward-compatible с предыдущим релизом.
- [ ] Обновлены entities, repos, DTO, tests, docs.

### 13.13 Anti-patterns / dangerous migrations

- ❌ Drop колонки + изменение кода в одном релизе.
- ❌ `ALTER TABLE ... TYPE ...` для большой таблицы без `USING` и без оценки времени блокировки.
- ❌ NOT NULL без default на непустой таблице.
- ❌ `doctrine:schema:update --force` где угодно.
- ❌ Миграция и backfill в одной транзакции при большой таблице.
- ❌ Удаление таблицы без записи в [36-backup-restore](36-backup-restore.md).

### 13.14 Safe migration examples

- Добавление nullable-поля + индекс — один шаг.
- Переименование поля: добавить новое → backfill → переключить код → удалить старое (за 3 релиза).
- Добавление таблицы с FK на nullable-колонку — безопасно.

### 13.15 Связанные документы

- [17-doctrine-and-database](17-doctrine-and-database.md)
- [18-migrations](18-migrations.md)
- [34-deployment](34-deployment.md)
- [36-backup-restore](36-backup-restore.md)

---

## 14. Playbook: WordPress migration-related change

> **Цель:** перенести контент с действующего WordPress (`zaborprofil.ru`) **без потери индексации**.

### 14.1 Принципы миграции

- **URL-структура сохраняется.** Каждому существующему URL соответствует либо живой URL в новой системе, либо 301 редирект на новый.
- **SEO metadata переносится.** Title, meta description, h1, canonical — сохраняются 1:1 либо обновляются осознанно.
- **Контент переносится явно**, не автоматическим импортером WordPress (импорт ad-hoc-инструментами, без интеграции в основное приложение).
- **Старые ссылки никогда не возвращают 404 без 301/410.**

### 14.2 Mapping rules

- Создать таблицу/файл `wp_url -> new_url` для каждого URL'а старого сайта.
- Хранить mapping как часть `redirects` подсистемы (см. [REDIRECTS.md](legacy/REDIRECTS.md), [16-routing](16-routing.md)).
- Для удалённых страниц — 410 Gone; для перемещённых — 301.

### 14.3 Перенос страниц

- Контентные страницы создаются как `Page` entity через admin.
- Контент верстается блоками (`hero`, `text`, `seo_text`, `faq`).
- HTML из WordPress санитизируется (никаких `<script>`, никаких inline стилей с magic).

### 14.4 Перенос SEO metadata

- Title / meta description / canonical / OG-теги — переносятся в `Page.metaTitle/metaDescription/canonicalOverride/...`.
- Структурированные данные (JSON-LD) — пересобираются на новой стороне (новый шаблон → новый JSON-LD), но содержание совпадает.

### 14.5 Перенос redirects

- Существующие редиректы из WordPress (плагинов redirection, .htaccess) — экспортируются и применяются в новой системе.
- Цепочки редиректов схлопываются (`A → B → C` ⇒ `A → C`).

### 14.6 Сохранение URL-структуры

- Перед миграцией — снимок `sitemap.xml` старого сайта. Это эталон.
- После миграции — sitemap нового сайта проверяется на coverage старого + явные 301 для отсутствующих.
- URL'ы сравниваются попарно (диф `wp_urls.txt` vs `new_urls.txt`).

### 14.7 Не терять индексацию

- Серверный 301 (не meta-refresh).
- Robots.txt не блокирует основные секции.
- Sitemap отправляется в Яндекс.Вебмастер и Google Search Console.
- Logs мониторятся на 4xx/5xx после релиза.

### 14.8 Валидация migrated content

- Functional smoke: 50 случайных старых URL → 200 или 301-301-…-200.
- SEO snapshot: title/description/canonical соответствуют ожиданиям.
- Visual diff на нескольких ключевых страницах.

### 14.9 Как фиксировать исключения

- Любая страница, которая **не** мигрирует «как было» — фиксируется в [REDIRECTS.md](legacy/REDIRECTS.md) с обоснованием.
- ADR в `docs/adr/` для крупных решений (например, перенос блогового раздела с агрессивным дедуплицированием).

### 14.10 Документировать решения миграции

- Список migrated URLs с mapping.
- Список 410 (удалено навсегда).
- Список особых случаев.

### 14.11 Связанные документы

- [01-product-purpose](01-product-purpose.md)
- [13-front-area](13-front-area.md)
- [16-routing](16-routing.md)
- [26-seo-architecture](26-seo-architecture.md)
- [REDIRECTS.md](legacy/REDIRECTS.md)

---

## 15. Playbook: SEO/search/indexing change

> **Warning.** Любое изменение публичного URL, canonical, robots, sitemap, JSON-LD или редиректов считается **production-risk изменением** и требует отдельной проверки. Откат за 5 минут невозможен — поисковик увидел.

### 15.1 Когда изменение имеет SEO impact

- Меняется публичный URL.
- Меняется meta title / description / canonical / robots.
- Меняется sitemap (источники, частота, lastmod).
- Меняется robots.txt.
- Меняются 301/410 редиректы.
- Меняется hreflang / alternate.
- Меняется JSON-LD / OpenGraph / structured data.
- Меняется заголовок страницы (h1) или иерархия hN.
- Меняется pagination или filter URL pattern.
- Меняется canonical для страниц с UTM/фильтрами.

### 15.2 Title / description / h1 / canonical

- Источник истины — `Page.metaTitle`, `Page.metaDescription`, `Page.h1`, `Page.canonicalOverride`.
- Если поле пустое — fallback по правилам в [SEO_GUIDE.md](legacy/SEO_GUIDE.md).
- Длины: title 50–65 символов, description 140–160. Превышение — warning.
- Canonical всегда абсолютный URL `https://zaborprofil.ru/...`.

### 15.3 Sitemap

- Динамический генератор по источникам (`SitemapSourceProviderInterface`).
- Кэшируется в `cache.seo`.
- Инвалидация — на каждое publish/unpublish/restore страницы.
- Размер `<= 50 000 URL` на файл; иначе — sitemap index.

### 15.4 Robots

- `robots.txt` — генерируется, не статичен.
- Disallow по env: `dev`/`staging` — `Disallow: /`. Prod — только служебные пути.
- Любое изменение `Disallow` в prod — explicit ADR.

### 15.5 Redirects

- Хранятся в БД (entity `Redirect` или аналог) либо в коде с явным списком.
- Только server-side 301/410. Никаких meta-refresh / JS-redirect.
- Цепочки запрещены: `A → B → C` всегда схлопывается.
- См. [REDIRECTS.md](legacy/REDIRECTS.md).

### 15.6 Сохранение public URL contracts

- Удалить публичный URL — это **действие, требующее отдельного ADR** и явного решения.
- Изменить slug — старый URL получает 301 на новый.

### 15.7 Noindex / nofollow

- `noindex` ставится только на сервисные страницы (поиск, фильтры с пустыми результатами, карточки в корзине).
- Никогда не оставлять `noindex` на основных контентных страницах.

### 15.8 Pagination / filter pages

- Pagination — `?page=2`, canonical на page 1 либо self-canonical (выбрать стратегию и зафиксировать в [SEO_GUIDE.md](legacy/SEO_GUIDE.md)).
- Фильтры — обычно `noindex,follow` либо canonical на базовый URL.

### 15.9 Structured data / JSON-LD

- Источник — view model, не Twig.
- Валидируется по `schema.org`.
- Минимум: `Organization`, `WebSite`, `BreadcrumbList`, `Product`/`Service` где применимо.

### 15.10 Тестирование SEO changes

- Functional: 200, корректный canonical, корректный title/description, JSON-LD парсится.
- SEO snapshot: zaborprofil.ru up → snapshot после изменения сравнивается с baseline (важных страниц).
- Sitemap: ожидаемые URL присутствуют, отсутствующие — отсутствуют.

### 15.11 Документирование SEO решений

- ADR при значимом изменении (canonical strategy, robots strategy, hreflang).
- [SEO_GUIDE.md](legacy/SEO_GUIDE.md) — оперативная инструкция редактору.
- [26-seo-architecture](26-seo-architecture.md) — техническая архитектура.

### 15.12 Связанные документы

- [16-routing](16-routing.md)
- [26-seo-architecture](26-seo-architecture.md)
- [SEO_GUIDE.md](legacy/SEO_GUIDE.md)
- [REDIRECTS.md](legacy/REDIRECTS.md)
- [37-runbooks](37-runbooks.md)

---

## 16. Playbook: Twig/frontend asset change

### 16.1 Когда менять Twig

- Меняется HTML-структура / layout / partial / component.
- Меняется отображение data, без изменения contract на view model.

### 16.2 Когда менять layout

- Глобальный header/footer/main grid.
- Нужно подключить новый asset bundle (Vite entry).
- Изменение SEO-блока (мета-теги, canonical, JSON-LD).

### 16.3 Когда менять partial / component

- Локальный кусок UI повторяется в 2+ местах — выделять partial.
- Изменение касается одного блока без влияния на layout.

### 16.4 Когда менять frontend assets (`assets/site`, `assets/admin`)

- Меняется CSS/JS/Tailwind конфиг.
- Меняется entry point Vite.
- Добавляется JS-модуль (валидаторы форм, lightbox, lazy load).

### 16.5 Когда менять Vite / Vue / Tailwind

- Vite — изменение entry/output, разделение бандлов, плагины.
- Vue — изменение admin SPA.
- Tailwind — конфиг тем, brand colors, новые plugins (`@tailwindcss/typography` уже подключен).

### 16.6 Не размещать бизнес-логику в шаблонах

- ❌ `{% set products = entity.repository.findActive() %}`.
- ❌ Сложные `{% if/for %}` с бизнес-условиями.
- ✅ View model подаётся в шаблон уже готовый.

### 16.7 Проверки

- **Responsive layout:** breakpoint'ы 320, 414, 768, 1024, 1280, 1920 — без horizontal scroll.
- **SEO structure:** один h1 на странице, корректная hN-иерархия, alt у изображений.
- **Forms:** CSRF token, корректные `name`, корректная валидация на сервере.
- **Asset build:** `npm run build` без ошибок, фингерпринты обновились.
- **Lint:twig:** `bin/console lint:twig templates/`.
- **Stimulus / Vue:** компонент монтируется без ошибок в консоли.

### 16.8 Обновление docs

- [21-templates-and-twig](21-templates-and-twig.md) — конвенции Twig.
- [22-frontend-assets](22-frontend-assets.md) — конвенции Vite/Vue/Tailwind.
- Скриншоты — только если меняется UX и описано в product-документации.

### 16.9 Связанные документы

- [21-templates-and-twig](21-templates-and-twig.md)
- [22-frontend-assets](22-frontend-assets.md)
- [19-forms-dto-validation](19-forms-dto-validation.md)

---

## 17. Playbook: files/uploads change

### 17.1 Когда изменение относится к uploads

- Появляется новая точка загрузки.
- Меняются правила валидации (MIME, extension, size).
- Меняется хранение (public/private, локальное/удалённое).
- Меняется доступ (URL, signed URL, gated download).

### 17.2 Валидация файлов (обязательная)

- **Extension** — whitelist (`jpg`, `jpeg`, `png`, `webp`, `pdf`, …).
- **MIME** — определяется из содержимого (`finfo`/Symfony `MimeTypeGuesser`), а не из заголовка.
- **Size** — лимит в bytes, явный.
- **Image dimensions** — для изображений (max width/height, чтобы не подложили 50000×50000 «бомбу»).
- **Magic bytes** — для критичных типов (PDF/zip).

### 17.3 Защита от path traversal

- Никогда не использовать пользовательский `originalName` как имя файла на диске.
- Имя файла на диске — детерминированный hash (`sha256(content)`) или UUID.
- `realpath` проверяется на принадлежность storage root.

### 17.4 Public vs private storage

- **Public** (картинки на сайте) — каталог, отдаваемый Nginx напрямую.
- **Private** (документы пользователей, бэкапы) — каталог **вне** web root, отдача через PHP с проверкой доступа.
- Никогда не класть private-файлы в `public_html/` без gating.

### 17.5 Backup / restore

- Uploads — отдельный backup-pipeline (см. [36-backup-restore](36-backup-restore.md)).
- Не предполагать, что `git`/деплой переносит файлы.

### 17.6 Cleanup

- Удалённые из БД файлы — удаляются с диска (через выделенный handler / async messenger).
- Orphan-cleanup — отдельная Console-команда, запускается по расписанию.

### 17.7 Не ломать ссылки на файлы

- URL файла — стабильный, основан на детерминированном id.
- При перегенерации (resize/optimize) — старый URL остаётся либо отдаёт 301.

### 17.8 Тестирование

- Functional: загрузка, валидация (positive/negative), доступ private-файлов под разными ролями.
- Integration: storage adapter (локальный / S3-совместимый, если используется).

### 17.9 Связанные документы

- [25-files-and-uploads](25-files-and-uploads.md)
- [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md)
- [36-backup-restore](36-backup-restore.md)
- [20-security-and-access-control](20-security-and-access-control.md)

---

## 18. Playbook: configuration/environment change

### 18.1 Когда добавлять новую env variable

- Появляется внешняя зависимость (URL, токен, провайдер).
- Появляется feature flag или операционный параметр (timeout, batch size, TTL).
- Появляется отличие dev/staging/prod, которое нельзя зашить в коде.

### 18.2 Как выбрать default

- Безопасный default = **минимально опасный** в случае забытой настройки.
- Для bool — `false` (отключено) по умолчанию.
- Для timeouts — небольшие значения (5–10s), а не «бесконечность».
- Для DSN — явный required без default; проверка в `EnvVarProcessor` или kernel boot.

### 18.3 Валидация config

- Использовать Symfony `Configuration` для bundle / `framework.yaml` `secrets`.
- Required env проверяется в `config/services.yaml` или `kernel.boot()`.
- Никогда не считать `getenv('FOO')` напрямую в коде. Только через DI parameters.

### 18.4 Обновить .env / .env.example / deploy templates

| Файл | Что обновить |
|------|--------------|
| `.env` (template на проде) | Добавить переменную с placeholder/комментарием |
| `.env.example` | Добавить переменную с дефолтом и комментарием |
| `.env.local` (dev) | Добавить локально, не коммитить |
| `.env.test` | Добавить тестовое значение |
| `deploy/.env.production.template` | Добавить переменную |
| `tools/deploy/*.sh` | Если требует валидации перед стартом |
| `.github/workflows/*.yml` | Если нужна в CI (через secrets) |

### 18.5 Обновить README / docs / install scripts / GitHub Actions

- [27-config-and-env](27-config-and-env.md) — список всех env variables проекта.
- [DEPLOY_VARIABLES.md](legacy/DEPLOY_VARIABLES.md) — env'ы для деплоя.
- [INSTALL.md](legacy/INSTALL.md) / [33-local-development](33-local-development.md) — инструкции для разработчика.
- [35-cicd](35-cicd.md) — секреты CI.

### 18.6 Избежать конфиг-хаоса

- Каждая env переменная имеет владельца (модуль / shared layer).
- Имя env: `<MODULE>_<PARAM>` (например, `MAILER_DSN`, `SEARCH_INDEX_NAME`).
- Не плодить дубликаты (`SEARCH_HOST` + `SEARCH_URL`) — выбрать один источник.

### 18.7 Не логировать secrets

- Никогда не выводить `$_ENV` целиком в логи.
- Sensitive env'ы (`*_TOKEN`, `*_SECRET`, `*_PASSWORD`, `*_DSN`) маскировать в логах через `Monolog Processor`.

### 18.8 dev / test / prod

- Каждое окружение имеет явный профиль env.
- Test env (`.env.test`) — отдельная БД, отдельный Redis db / namespace.
- Prod env — только через secrets management (см. [DEPLOY_VARIABLES.md](legacy/DEPLOY_VARIABLES.md)).

### 18.9 Связанные документы

- [27-config-and-env](27-config-and-env.md)
- [33-local-development](33-local-development.md)
- [34-deployment](34-deployment.md)
- [35-cicd](35-cicd.md)
- [DEPLOY_VARIABLES.md](legacy/DEPLOY_VARIABLES.md)

---

## 19. Playbook: cache/Redis change

### 19.1 Когда использовать Symfony Cache

- HTTP fragment caching, view-model caching, computed-data caching.
- TTL-based, ключи под управлением приложения.

### 19.2 Когда использовать Redis напрямую

- Когда нужны продвинутые структуры (sorted set, stream).
- Очереди Messenger (Doctrine transport — основной, Redis — опционально).
- Rate limiter, lock, session.

### 19.3 Cache pools

- `cache.app` — общий fallback-пул.
- `cache.seo` — sitemap, redirects, canonical.
- `cache.content` — view-model страниц.
- `cache.system` — Symfony system cache (annotations/config).
- Новый pool — отдельная декларация в `config/packages/cache.yaml` + DI tag.

### 19.4 Cache keys

- Префикс модуля: `seo.sitemap.products.v2`, `content.page.<id>.v1`.
- Версионный суффикс (`.v1`, `.v2`) — для bulk-invalidation через смену версии.
- Никогда не использовать пользовательские ввод данные напрямую в ключе без хеширования.

### 19.5 Invalidation

- **Каждый pool** имеет владельца и явные правила инвалидации:
  - На какое событие инвалидируется (publish/unpublish/edit).
  - Полная инвалидация vs точечная.
- Использовать tags (Symfony Cache `TagAwareCacheInterface`) для группировки.

### 19.6 Stale data

- Stale-while-revalidate — для тяжёлых операций.
- Cold cache acceptable: первая запросчик платит, остальные получают cached.
- Никаких permanent caches без TTL и без инвалидации.

### 19.7 User-specific / private data

- Private data **не** кэшируется в shared pool без user id в ключе.
- Лучше: per-request memoization, не Redis.
- Никогда не кэшировать sensitive (auth tokens, personal data) в Redis с длинным TTL.

### 19.8 Тестирование cache behavior

- Integration: hit / miss / invalidation сценарий.
- Functional: при наличии cache — поведение идентично без cache (correctness не зависит от наличия).

### 19.9 Документирование TTL/invalidation

- В коде: PHPDoc на сервис, описание ключей и TTL.
- В docs: [23-cache-and-redis](23-cache-and-redis.md) — таблица pools / keys / TTL / invalidation.

### 19.10 Связанные документы

- [23-cache-and-redis](23-cache-and-redis.md)
- [28-logging-observability](28-logging-observability.md)

---

## 20. Playbook: Messenger/worker change

### 20.1 Message lifecycle

`dispatch → transport → consumer → handler → ack/nack → retry/dlq`

- Dispatch — из use case или event listener.
- Transport — Doctrine (default) или Redis.
- Handler — `#[AsMessageHandler]`.
- Retry — конфигурируется на transport.
- DLQ (failed transport) — обязателен.

### 20.2 Handler responsibilities

- Один handler = одна операция.
- Идемпотентен (повторное выполнение того же message не вредит).
- Логирует start / ok / fail с message id.
- Бросает retryable / non-retryable exceptions явно.

### 20.3 Doctrine transport implications

- Storage — таблица в той же БД. Учитывается при backup'ах.
- Не использовать `messenger:consume --time-limit=0` — нужно ограничивать lifetime для systemd-restart.
- Размер таблицы мониторится; orphans чистятся.

### 20.4 Retry policy

- `max_retries` — явное число (3–5).
- `delay` + `multiplier` — экспоненциальный backoff.
- Non-retryable exceptions (`UnrecoverableMessageHandlingException`) — сразу в DLQ.

### 20.5 Idempotency

- Каждое message имеет ключ идемпотентности (message id или business key).
- Handler проверяет, не выполнялся ли ранее (через outbox/processed-table) либо опирается на natural idempotency операции.

### 20.6 Duplicate protection

- Producer не дублирует messages без причины.
- Handler толерантен к дублям.
- Алерт при росте duplicate-rate.

### 20.7 Graceful shutdown

- `messenger:consume --time-limit=3600 --memory-limit=128M` — typical.
- systemd unit с `Restart=always` и `WatchdogSec`.
- Long-running operations внутри handler — с прогресс-логом и promptable shutdown.

### 20.8 Partial failure

- Handler пишет только в одну транзакционную границу. Внешние side effects (HTTP, mail) — после commit.
- При partial failure — message retryable, идемпотентность гарантирует корректность.

### 20.9 Status transitions

- Если message изменяет статус сущности — fixed state machine в Domain. Никаких ad-hoc statuses.

### 20.10 Impact on DB

- Doctrine transport создаёт нагрузку на БД. Большие очереди — рассмотреть Redis transport.
- Retry storms могут переполнить БД — alerts на queue depth.

### 20.11 Impact on logs / monitoring

- Channel `messenger`. Поля: `message_class`, `message_id`, `attempt`, `duration_ms`.
- Алерты: queue depth, DLQ size, retry rate.

### 20.12 Impact on deploy / systemd

- Worker — отдельный systemd unit.
- При деплое: graceful stop → миграции → старт worker'ов.
- Несовместимое изменение message — рассмотреть deprecation (handler старого формата + новый формат).

### 20.13 Связанные документы

- [24-messenger-and-queues](24-messenger-and-queues.md)
- [28-logging-observability](28-logging-observability.md)
- [34-deployment](34-deployment.md)
- [37-runbooks](37-runbooks.md)

---

## 21. Playbook: logging/observability change

### 21.1 Когда добавлять новый лог

- Старт / завершение / ошибка use case.
- Старт / завершение / ошибка messenger handler.
- Внешний HTTP-вызов (success/fail/timeout).
- Security event (login, failed login, password reset, role change, suspicious upload).
- Domain event значимый для бизнеса (publish, archive, refund).

### 21.2 Какие поля добавлять

| Поле | Когда |
|------|-------|
| `request_id` | Каждый HTTP request |
| `correlation_id` | Все логи в рамках одной операции (включая messenger) |
| `user_id` | Если есть аутентифицированный пользователь |
| `entity_id` | Если операция касается сущности |
| `job_id` / `message_id` | Для messenger |
| `module` | Имя модуля |
| `duration_ms` | Для значимых операций |
| `level` | `info`/`warning`/`error`/`critical` |
| `channel` | `app`, `business`, `security`, `messenger`, `integration` |

### 21.3 Логирование ошибок

- `error` — операция упала, требует внимания.
- `critical` — операция упала, требует немедленного действия (DB down, prod-mailer fail).
- Stack trace — только в structured field, не в `message`.
- Никаких sensitive данных в логах.

### 21.4 Не логировать секреты

- Токены, пароли, DSN, PII (телефон, email где не требуется) — маскировать через Monolog processor.
- При сериализации DTO — отдельный sanitizer.

### 21.5 Логирование domain / application / infrastructure errors

- Domain — `info`/`warning`, обычно ожидаемые ошибки бизнес-правил.
- Application — `warning`/`error` при сбое use case.
- Infrastructure — `error`/`critical` при отказе адаптера (DB, Redis, HTTP).

### 21.6 Когда обновлять runbooks / docs

- Любой новый алерт ⇒ runbook в [37-runbooks](37-runbooks.md).
- Любой новый канал ⇒ запись в [28-logging-observability](28-logging-observability.md).

### 21.7 Полезные production логи

- Для каждой операции: что сделано / на ком / сколько заняло / результат.
- Никаких `'something happened'` без контекста.

### 21.8 Связанные документы

- [28-logging-observability](28-logging-observability.md)
- [LOGGING.md](legacy/LOGGING.md)
- [30-error-handling](30-error-handling.md)
- [37-runbooks](37-runbooks.md)

---

## 22. Playbook: healthcheck change

### 22.1 Когда менять /health

- Появляется новая критичная зависимость (Redis, S3, внешний API).
- Меняется набор критичных проверок при старте сервиса.
- Появляются readiness vs liveness разделения.

### 22.2 Какие runtime checks добавлять

- DB connectivity (`SELECT 1`).
- Redis ping.
- Filesystem (writable uploads dir).
- Critical config (env присутствует).
- Внешние зависимости — **только** в readiness (не в liveness).

### 22.3 База данных

- Лёгкий `SELECT 1` без query на бизнес-таблицы.
- Timeout — короткий (1–2s).

### 22.4 Redis

- `PING` через injected client.
- Не дёргать критичные ключи.

### 22.5 Filesystem / uploads

- `is_writable($uploadsRoot)`.
- Не пытаться писать тестовый файл — это добавляет I/O в каждый health request.

### 22.6 External dependencies

- **Не включать** в `/health/live` (liveness). Внешний API down ≠ наш сервис down.
- Включать в `/health/ready` (readiness) — если без него сервис не функционален.

### 22.7 Не делать healthcheck тяжёлым

- Целевое время ответа — < 200ms.
- Никаких сложных queries.
- Никаких HTTP-вызовов с большими таймаутами.

### 22.8 Liveness vs readiness

- **Liveness** (`/health/live`) — процесс жив, можно не убивать. Минимальные проверки.
- **Readiness** (`/health/ready`) — готов принимать трафик. Включает зависимости.

### 22.9 Deploy checks

- После релиза deploy script бьёт `/health/ready` — если не 200 за N секунд, alert + rollback.

### 22.10 Связанные документы

- [29-healthchecks](29-healthchecks.md)
- [34-deployment](34-deployment.md)
- [37-runbooks](37-runbooks.md)

---

## 23. Playbook: error handling change

### 23.1 User-facing errors (Front)

- Никогда не показывать stack trace.
- 4xx — дружелюбная страница (404, 403, 400) с сохранением layout и SEO (минимум canonical, robots noindex).
- 5xx — статичная error page или минимальный fallback (без зависимости от БД/Redis).
- Логировать root cause.

### 23.2 Admin errors

- Подробное сообщение (admin доверяемый), но без leak sensitive.
- Flash сообщения вместо exception page.
- Audit-лог для опасных операций.

### 23.3 API errors

- Единый JSON-формат (см. [Раздел 9.4](#94-error-format)).
- HTTP status соответствует семантике.
- Никогда не возвращать stack trace в response body.

### 23.4 Infrastructure errors

- Адаптер → бросает infrastructure exception → обрабатывается выше.
- Ретраи на уровне Messenger / HTTP client.
- Алерты на rate ошибок, не на каждое event.

### 23.5 Не показывать stack trace пользователю

- В `prod` env — обязательно. Symfony делает это автоматически при `APP_ENV=prod`.
- Кастомные exception listeners — не должны раскрывать stack trace.

### 23.6 Логировать root cause

- `previous` exception — обязательно сохраняется.
- Контекст (`user_id`, `entity_id`, `request_id`) — в structured fields.

### 23.7 Safe fallback

- Если падает один блок страницы (например, sidebar) — страница рендерится без него.
- Esi/fragment fallback — стратегия в [21-templates-and-twig](21-templates-and-twig.md).

### 23.8 Тестирование ошибок

- Functional: 404, 403, 422, 500 — корректный response, корректный лог.
- Negative path тесты для каждого нового use case.

### 23.9 Связанные документы

- [30-error-handling](30-error-handling.md)
- [28-logging-observability](28-logging-observability.md)

---

## 24. Playbook: Docker / Compose / local environment change

> **Note.** Docker и `docker-compose` используются **только** в local/dev. Production/staging — native VPS stack. Не превращать local Docker config в шаблон prod.

### 24.1 Когда менять Dockerfile

- Меняется PHP/Node/Nginx/Redis/Postgres версия.
- Добавляется PHP-расширение (`pdo_pgsql`, `redis`, `intl`).
- Меняется системная зависимость для composer/npm.

### 24.2 Когда менять docker-compose

- Добавляется/убирается сервис (например, mailhog/maildev).
- Меняются volumes, ports, env passthrough.
- Меняются healthchecks или depends_on.

### 24.3 Volumes

- `./` смонтирован в контейнер app — изменения PHP-кода применяются live.
- БД и Redis — named volumes, не bind-mount (производительность).
- Uploads — local bind-mount для удобства.

### 24.4 Networks

- Все сервисы в одной compose network.
- Имена сервисов — DNS-имена (`postgres`, `redis`, `app`, `nginx`).

### 24.5 Healthchecks

- Postgres / Redis / Nginx — healthcheck'ом выровнены `depends_on: condition: service_healthy`.

### 24.6 Restart policy

- Local — `unless-stopped` или `no` (по вкусу).
- Не наследовать в prod.

### 24.7 Secrets / env

- Локальный `.env.local` — основной источник.
- Никаких секретов в `docker-compose.yml`.

### 24.8 Asset build

- Vite dev server — отдельный сервис в compose либо из host.
- Production build — `npm run build` локально или в CI, не в dev compose.

### 24.9 Что обязательно тестировать

- `make build && make up` — успешный запуск с нуля.
- `make migrate` — миграции применяются.
- Открыть `http://localhost:<port>` — публичный сайт работает.
- `make down && make up` — повторный запуск без потерь данных (named volumes).

### 24.10 Не путать local Docker и production VPS

- Версии в Docker = версии прода (PHP 8.5+, PG 18+, Redis 8+, Node 25.9+, Nginx 1.30+).
- Конфиги (Nginx, PHP-FPM) — не общие. Local — упрощённый, prod — собранный из `deploy/*`.

### 24.11 Связанные документы

- [32-docker-architecture](32-docker-architecture.md)
- [33-local-development](33-local-development.md)
- [27-config-and-env](27-config-and-env.md)
- [LOCAL_DOCKER.md](LOCAL_DOCKER.md)

---

## 25. Playbook: Nginx / PHP-FPM / SSL change

### 25.1 Когда трогать Nginx

- Изменение public root / aliases.
- Новый location (api, admin, dev-only).
- Security headers (CSP, HSTS, X-Frame-Options).
- Static assets caching.
- gzip/brotli настройки.
- SSL/TLS / certbot rotation.
- Redirects на уровне сервера (HTTP → HTTPS, www → non-www).

### 25.2 Request flow ([07-request-flow](07-request-flow.md))

```
client → Nginx :443 → static? yes → отдать; нет → fastcgi_pass → PHP-FPM → public_html/index.php → Symfony Kernel
```

### 25.3 Public root

- Web root = `public_html/`. Не `public/`. Не корень репозитория.
- В `public_html/` — только `index.php` + symlink на `assets/...` для билда (либо публикация Vite в `public_html/build/`).

### 25.4 Symfony front controller

- Все non-static URL → `index.php`.
- `try_files $uri /index.php$is_args$args;`

### 25.5 Static assets

- Cache headers для `/build/` (Vite manifest immutable).
- Не отдавать `.env`, `.git`, `composer.json`, `node_modules`.

### 25.6 Uploads

- Public uploads — отдаются Nginx напрямую с правильным `Content-Type`.
- Private uploads — **никогда** не доступны Nginx; отдаются через PHP с auth.

### 25.7 Redirects

- HTTP → HTTPS — на уровне Nginx.
- www → non-www (или обратно) — Nginx, единая стратегия.
- 301 на уровне приложения (страничные редиректы) — Symfony controller.

### 25.8 SSL / certbot

- Сертификат через certbot.
- HSTS только после уверенности в HTTPS-only.
- Auto-renew — systemd timer.

### 25.9 Security headers

- `X-Frame-Options: SAMEORIGIN`.
- `X-Content-Type-Options: nosniff`.
- `Referrer-Policy: strict-origin-when-cross-origin`.
- `Permissions-Policy` — минимальные права.
- `Content-Security-Policy` — настраивается, тестируется на staging.

### 25.10 gzip / brotli

- gzip — для текстовых типов.
- brotli — если модуль доступен.
- Не сжимать уже сжатые (jpg, png, mp4).

### 25.11 Проверки после изменений

- `nginx -t` — синтаксис ok.
- `nginx -s reload` — без даунтайма.
- `curl -I https://...` — заголовки соответствуют.
- SSL Labs / Mozilla Observatory — для оценки конфигурации.
- Тестирование на staging до prod.

### 25.12 Связанные документы

- [07-request-flow](07-request-flow.md)
- [16-routing](16-routing.md)
- [26-seo-architecture](26-seo-architecture.md)
- [34-deployment](34-deployment.md)

---

## 26. Playbook: install/deploy script change

> **Warning.** Сломанный deploy-скрипт = downtime прода и поднятый вручную сервер ночью. Каждое изменение проверяется на staging.

### 26.1 Идемпотентность

- Повторный запуск скрипта на той же системе = тот же результат, без ошибок.
- Перед действием — проверять текущее состояние (`if [ ! -L /etc/nginx/sites-enabled/zaborprofil ]; then ...`).
- `mkdir -p`, `ln -sfn`, `cp -n` — идемпотентные команды.
- Никаких `rm -rf` на основе пользовательского ввода без проверки переменной (`: "${VAR:?VAR is required}"`).

### 26.2 Безопасное поведение

- `set -euo pipefail` — обязательно в bash.
- Никаких пустых переменных в `rm`/`cp` путях.
- Логирование всех значимых действий с timestamp.
- Dry-run опция (`--dry-run`) — желательна.

### 26.3 Повторный запуск

- Тестировать дважды подряд: первый запуск ставит, второй — ничего не ломает.

### 26.4 Dangerous actions

- DB drop / TRUNCATE / migration `down` — никогда без `--yes-i-really-mean-it`.
- `git clean -fdx` — никогда в release dir без явной защиты.
- Удаление старых релизов — оставлять минимум 3 предыдущих для rollback.

### 26.5 Help / messages / docs

- `--help` выводит список флагов и примеры.
- Сообщения логичные (`==> Applying migrations...`).
- Обновить [DEPLOY.md](legacy/DEPLOY.md), [INSTALL.md](legacy/INSTALL.md), [34-deployment](34-deployment.md).

### 26.6 Тесты

- **Fresh install** на чистой VM (или Docker container, эмулирующий VPS).
- **Update install** на старой версии (предыдущий релиз).
- **Rollback path** — переключение symlink на previous release.

### 26.7 Не ломать production server

- Перед опасным действием — бэкап (БД + uploads).
- Проверять размер свободного места на диске.
- Логировать каждый шаг в файл (`/var/log/zaborprofil/deploy-<timestamp>.log`).

### 26.8 Связанные документы

- [34-deployment](34-deployment.md)
- [36-backup-restore](36-backup-restore.md)
- [37-runbooks](37-runbooks.md)
- [DEPLOY.md](legacy/DEPLOY.md)
- [INSTALL.md](legacy/INSTALL.md)

---

## 27. Playbook: CI/CD change

### 27.1 GitHub Actions

- Изменение workflow тестируется через `workflow_dispatch` на feature branch.
- Job names — описательные.
- Reusable workflows — в `.github/workflows/_*.yml`.

### 27.2 Secrets

- Только через GitHub Secrets / Environment secrets.
- Никогда в YAML напрямую.
- Минимально необходимый набор секретов на job.

### 27.3 PHP version

- Совпадает с прод (8.5+).
- `setup-php` action с фиксированной версией.
- Расширения: `pdo_pgsql`, `redis`, `intl`, `mbstring`, `gd`, `zip`.

### 27.4 Node / npm version

- Node 25.9+, npm 11.12+.
- `setup-node` action с фиксированной версией.
- `npm ci` (а не `npm install`) для воспроизводимости.

### 27.5 PostgreSQL / Redis services

- Service containers с фиксированной версией (PG 18+, Redis 8+).
- Healthcheck перед запуском тестов.

### 27.6 Migrations

- В CI: `doctrine:migrations:migrate --no-interaction` на тестовой БД.
- Проверка идемпотентности: повторный run.

### 27.7 Deploy order

- `lint` → `test` → `build` → `deploy`.
- Deploy запускается **только** после успеха предыдущих stages.

### 27.8 Post-deploy checks

- `/health/ready` — wait until 200.
- Smoke test нескольких ключевых URL.

### 27.9 Failure handling

- Job failure → fail pipeline.
- Notifications — Slack / Telegram / email (см. [35-cicd](35-cicd.md), [CI_CD.md](legacy/CI_CD.md)).

### 27.10 Rollback hints

- В deploy job — explicit step для отката (например, переключить symlink).
- Manual rollback — кнопка `workflow_dispatch` rollback workflow.

### 27.11 Не делать CI слишком медленным

- Cache `composer` / `npm` / `vendor` / `node_modules`.
- Параллелизация stage'ов где возможно.
- Тяжёлые e2e — отдельный manual workflow.

### 27.12 Не пропускать critical checks

- `composer validate --strict` — обязательно.
- `vendor/bin/phpstan analyse` — обязательно.
- `vendor/bin/php-cs-fixer fix --dry-run` — обязательно.
- `vendor/bin/phpunit` — обязательно.
- `npm run build` — обязательно.
- Migration test — обязательно.

### 27.13 Связанные документы

- [35-cicd](35-cicd.md)
- [38-coding-standards](38-coding-standards.md)
- [31-testing-strategy](31-testing-strategy.md)
- [CI_CD.md](legacy/CI_CD.md)

---

## 28. What must be updated together

При изменении X — обязательно ревью/обновление Y и Z.

| Меняется | Также обновить |
|----------|----------------|
| Route / public URL | [16-routing](16-routing.md), [26-seo-architecture](26-seo-architecture.md), `redirects`, sitemap, functional test, [REDIRECTS.md](legacy/REDIRECTS.md) |
| Controller | Route, Twig template, Application service, functional test, controller docs |
| Application use case | Command/Query DTO, controller/API caller, unit + integration tests, [09-application-layer](09-application-layer.md) |
| Domain entity | Doctrine mapping, repository, migration, unit tests, [05-domain-model](05-domain-model.md), [10-domain-layer](10-domain-layer.md) |
| Twig layout | CSS / Tailwind, asset build, SEO structure, responsive checks, [21-templates-and-twig](21-templates-and-twig.md) |
| Vue / admin SPA | Vite build, admin API contract, permissions/voters, e2e admin tests, [22-frontend-assets](22-frontend-assets.md), [12-admin-area](12-admin-area.md) |
| Form / DTO | Validator, template / Vue form, controller, tests, [19-forms-dto-validation](19-forms-dto-validation.md) |
| Doctrine entity | Migration, fixtures, repositories, tests, [17-doctrine-and-database](17-doctrine-and-database.md), [18-migrations](18-migrations.md) |
| DB schema | Migration, entities, repositories, services, tests, deploy notes, [13. Раздел](#13-playbook-database-schema-change) |
| Env vars | `config/services.yaml` validation, `.env`, `.env.example`, `.env.test`, deploy templates, README, install scripts, CI secrets, [27-config-and-env](27-config-and-env.md), [DEPLOY_VARIABLES.md](legacy/DEPLOY_VARIABLES.md) |
| Redis / cache key | Invalidation hooks, tests, docs, [23-cache-and-redis](23-cache-and-redis.md), [44-troubleshooting](44-troubleshooting.md) |
| Messenger message | Handler, retry policy, transport routing, tests, logs, worker config, [24-messenger-and-queues](24-messenger-and-queues.md) |
| Uploads logic | Validators, security checks, backup/restore docs, cleanup, tests, [25-files-and-uploads](25-files-and-uploads.md), [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md), [36-backup-restore](36-backup-restore.md) |
| docker-compose | Local docs, install scripts, healthchecks, [32-docker-architecture](32-docker-architecture.md), [33-local-development](33-local-development.md), [LOCAL_DOCKER.md](LOCAL_DOCKER.md) |
| Nginx config | Routing/redirects, static assets, SSL, SEO checks, [34-deployment](34-deployment.md) |
| CI workflow | Secrets, deploy checklist, docs, [35-cicd](35-cicd.md), [CI_CD.md](legacy/CI_CD.md) |
| SEO metadata | Sitemap, canonical, JSON-LD, tests, [26-seo-architecture](26-seo-architecture.md), [SEO_GUIDE.md](legacy/SEO_GUIDE.md) |
| Voter / role | Firewall config, controller `#[IsGranted]`, tests, [20-security-and-access-control](20-security-and-access-control.md), [ROLES.md](legacy/ROLES.md) |
| Logging | Log channel, runbook, alert rules, [28-logging-observability](28-logging-observability.md), [37-runbooks](37-runbooks.md) |
| Healthcheck | Deploy script (post-deploy check), runbook, [29-healthchecks](29-healthchecks.md) |
| Module | `services.yaml`, autoloading, README модуля, [MODULES.md](legacy/MODULES.md), [06-module-architecture](06-module-architecture.md), [43-module-development-guide](43-module-development-guide.md) |

---

## 29. Common implementation anti-patterns

Список того, что **запрещено** в этом проекте.

- **Менять всё сразу.** Большой PR без логической связности — auto-block.
- **Рефакторить без запроса.** Неиспрошенный refactor смешан с feature → нельзя ревьюить.
- **Писать фичу в wrong layer.** Бизнес-логика в Twig/controller; Doctrine в Domain; HTTP в Application.
- **Смешивать controller и business logic.** Контроллер обязан быть тонким.
- **Смешивать Twig и domain logic.** Шаблон не вызывает entity-методы с side effects.
- **Смешивать Admin и Front behavior.** Контроллеры зон не наследуются друг от друга.
- **Смешивать API contract и internal entity.** API возвращает DTO, не Doctrine entity.
- **Менять public URL без SEO analysis.** 301-редирект обязателен.
- **Менять schema без thought process.** Неразделённый additive/mutating migration.
- **Менять deploy без проверки идемпотентности.** Скрипт упадёт при повторе.
- **Silent changes to cache semantics.** Поменялся TTL/ключ без обновления invalidation.
- **Silent changes to Messenger retry.** `max_retries: 100` «на всякий».
- **Broad compose edits.** Полная переделка `docker-compose.yml` на одну фичу.
- **Unsafe shell scripts.** `rm -rf $VAR/` без `: "${VAR:?...}"`.
- **Unsafe uploads.** Без MIME/extension/size, с пользовательским filename как путь.
- **Placeholder docs.** `# TODO: write later` — это битая документация.
- **Missing tests.** «Я локально проверил» — это не тест.
- **Игнорирование migration / backups impact.** Migration без `down`/backup плана.
- **Hardcoded env / secrets.** `define('API_KEY', '...')` — categorical no.
- **Logging sensitive data.** Токен/пароль/PII в логе.
- **Игнорирование rollback.** «Откатим — придумаем» — нет, не придумаем.

---

## 30. Common mistakes by AI agents

Эти ошибки делают **исключительно** AI-агенты. Cursor и аналогичные инструменты обязаны их избегать явно.

- **Over-refactoring.** Сделать «лучше», чем просили — нет.
- **Hidden coupling.** Импорт класса соседнего модуля «потому что DI» — нарушение module boundary.
- **No impact analysis.** Сразу пишет код без planning note.
- **Bad layer placement.** Создаёт `Service` в произвольной директории, без понимания application/domain/infrastructure.
- **No migration reasoning.** Меняет entity, не пишет миграцию.
- **Broken docs parity.** Меняет код, документация устаревает.
- **Missing logging.** Новый use case без `logger->info(...)`.
- **Wrong assumptions about Symfony.** Использует deprecated/обобщённые конвенции (`Symfony 5/6`) вместо актуальных Symfony 8.x.
- **Wrong assumptions about Doctrine lifecycle.** Игнор `flush`-границ, `unitOfWork`, lazy.
- **Wrong assumptions about Twig.** Бизнес-логика в шаблоне; вызов repository.
- **Wrong assumptions about Vite/Vue/Tailwind.** Создаёт inline `<style>`, игнорирует Tailwind utilities.
- **Incorrect retry behavior.** `max_retries: 0` или `1000` — оба плохие.
- **Cache invalidation bugs.** Кеш всегда «потеплеет», invalidation потом.
- **Silently changing env contracts.** Добавляет env, не обновляет `.env.example`.
- **Breaking public URLs.** Переименовывает route name → роутер ломается, sitemap пуст.
- **Creating fake docs without checking actual files.** Ссылается на `docs/seo-bigrules.md`, которого нет.
- **Ignoring existing project structure.** Создаёт `app/Http/Controller/...` (Laravel-style) в Symfony-проекте.
- **Adding abstractions without real need.** «На случай, если…» — никогда не стоит того.
- **Использование WordPress/Laravel паттернов.** Hooks/shortcodes/Eloquent — не существует в этом проекте.
- **Использование EasyAdmin как primary admin.** Запрещено, см. [project-overview](../.cursor/rules/project-overview.mdc).
- **Создание Docker-зависимости в prod.** Docker — только local/dev.
- **Игнор русскоязычной документации.** Все `docs/*.md` — на русском (термины stack — на английском).

---

## 31. Checklists

Каждый чек-лист — рабочий инструмент. Не декорация. Перед коммитом — пройти соответствующие.

### 31.1 General change checklist

- [ ] Задача классифицирована по [matrix](#4-task-classification-matrix).
- [ ] Прочитаны соответствующие docs.
- [ ] Planning note составлен (если нетривиальная задача).
- [ ] Слой/модуль выбран корректно.
- [ ] Diff минимален; нет unrelated changes.
- [ ] Тесты обновлены (unit / integration / functional).
- [ ] Документация обновлена.
- [ ] Конфиги/env обновлены.
- [ ] Миграции применяются и откатываются.
- [ ] Линтеры/статанализ проходят (`composer check:syntax`, `phpstan`, `php-cs-fixer`, `rector --dry-run`).
- [ ] `phpunit` зелёный.
- [ ] `npm run build` проходит.
- [ ] Rollback plan описан.

### 31.2 Feature checklist

- [ ] Цель сформулирована (1 предложение).
- [ ] User-facing impact описан.
- [ ] Application use case спроектирован.
- [ ] Domain изменения минимальны и инвариантны.
- [ ] DTO input/output присутствуют.
- [ ] Controller тонкий.
- [ ] Validation присутствует.
- [ ] Security проверён.
- [ ] Logging добавлен.
- [ ] Tests покрывают happy + negative path.
- [ ] Docs обновлены.

### 31.3 Public page checklist

- [ ] URL не конфликтует с другими маршрутами.
- [ ] Canonical задан (или fallback корректен).
- [ ] Title / description в пределах лимитов.
- [ ] Один h1 на странице.
- [ ] Breadcrumbs присутствуют (если применимо).
- [ ] Sitemap включает страницу.
- [ ] Robots не блокирует страницу (если индексация ожидаема).
- [ ] Functional test 200 + meta.
- [ ] Mobile responsive.
- [ ] SEO docs обновлены.

### 31.4 Admin feature checklist

- [ ] Voter / `#[IsGranted]` присутствует.
- [ ] CSRF token (для HTML-форм).
- [ ] DTO + Validator.
- [ ] Use case в Application layer.
- [ ] Redirect-after-POST.
- [ ] Flash сообщения.
- [ ] Audit log (для опасных операций).
- [ ] Functional test admin.
- [ ] Admin docs обновлены.

### 31.5 API checklist

- [ ] Контракт описан (request/response/error).
- [ ] Auth настроен.
- [ ] Rate limiting (для public API).
- [ ] DTO input/output (без Doctrine entity).
- [ ] Validator.
- [ ] Единый error format.
- [ ] Versioning (/api/v1/...).
- [ ] OpenAPI обновлён (если есть).
- [ ] Functional test (success + 4xx + 5xx).

### 31.6 Application layer checklist

- [ ] Один handler — одна операция.
- [ ] Command/Query разделены.
- [ ] DTO без Doctrine entity / Request.
- [ ] Транзакционная граница в handler.
- [ ] Side effects через интерфейсы.
- [ ] Unit handler test.
- [ ] Logging start/ok/fail.

### 31.7 Domain change checklist

- [ ] Инварианты в методах поведения, не в setter'ах.
- [ ] VO `final readonly`.
- [ ] Domain не использует Symfony container.
- [ ] Unit test (happy + invariant violation + boundary).
- [ ] Business rule задокументирован.

### 31.8 Doctrine / entity checklist

- [ ] Маппинг соответствует миграции.
- [ ] Индексы на новые поля фильтра/сортировки.
- [ ] FK с явной ON DELETE стратегией.
- [ ] Repository метод покрыт integration test.
- [ ] N+1 проверен (`addSelect` / `JOIN FETCH` где нужно).

### 31.9 Migration checklist

- [ ] Миграция читается глазами (без лишних DROP/RENAME).
- [ ] Additive шаги отделены от mutating.
- [ ] Backfill — отдельным шагом, батчами.
- [ ] NOT NULL — после backfill.
- [ ] `down()` реализован или явно forward-only.
- [ ] Тестирована up → down → up.
- [ ] Backward-compatible с предыдущим релизом.

### 31.10 SEO checklist

- [ ] Public URL не изменился (или 301 настроен).
- [ ] Canonical корректен.
- [ ] Title / description в пределах лимитов.
- [ ] h1 единственный, hN-иерархия консистентна.
- [ ] Sitemap содержит/не содержит ожидаемое.
- [ ] Robots не блокирует.
- [ ] JSON-LD валиден.
- [ ] hreflang (если применимо).
- [ ] SEO docs обновлены.

### 31.11 Frontend assets checklist

- [ ] `npm run build` проходит.
- [ ] Vite manifest обновлён.
- [ ] Tailwind purge захватывает новые классы.
- [ ] Lint:twig проходит.
- [ ] Responsive проверен.
- [ ] Accessibility minimum (alt, aria, contrast).

### 31.12 Files / uploads checklist

- [ ] Extension whitelist.
- [ ] MIME из контента (не header).
- [ ] Size limit.
- [ ] Image dimensions limit.
- [ ] Filename — детерминированный hash/UUID.
- [ ] Public/private разделено.
- [ ] Backup захватывает uploads.
- [ ] Cleanup для orphans.

### 31.13 Cache / Redis checklist

- [ ] Pool назван и зарегистрирован.
- [ ] Ключ содержит префикс модуля и версию.
- [ ] TTL задан.
- [ ] Invalidation event описан.
- [ ] Не кэшируется sensitive / user-specific в shared pool.
- [ ] Integration test hit/miss.

### 31.14 Messenger / worker checklist

- [ ] Message — `final readonly`.
- [ ] Handler `#[AsMessageHandler]`.
- [ ] Идемпотентность гарантирована.
- [ ] Retry policy явная.
- [ ] DLQ настроен.
- [ ] Logging start/ok/fail с message_id.
- [ ] Functional test через `in-memory://`.
- [ ] Worker systemd unit обновлён (если новый message требует).

### 31.15 Healthcheck checklist

- [ ] `/health/live` — ≤ 200ms.
- [ ] `/health/ready` включает критичные deps.
- [ ] Внешние deps — только в readiness.
- [ ] Deploy script проверяет ready после релиза.

### 31.16 Deploy checklist

- [ ] Идемпотентный скрипт.
- [ ] `set -euo pipefail`.
- [ ] Бэкап БД перед migrate.
- [ ] Migrations применяются.
- [ ] Перезапуск PHP-FPM / worker'ов.
- [ ] `/health/ready` 200 после релиза.
- [ ] Symlink на новый release (atomic switch).
- [ ] Старые релизы сохранены (минимум 3).
- [ ] Документация deploy обновлена.

### 31.17 Docker / Compose checklist

- [ ] Версии стека = prod.
- [ ] Healthchecks между сервисами.
- [ ] `make build && make up` чистый.
- [ ] Не используется в prod.

### 31.18 Nginx / SSL checklist

- [ ] `nginx -t` ok.
- [ ] HTTP→HTTPS redirect.
- [ ] HSTS (после уверенности в HTTPS).
- [ ] Security headers.
- [ ] Static assets cache.
- [ ] Uploads (public/private) разделены.
- [ ] Symfony front controller fallback.
- [ ] Тест на staging до prod.

### 31.19 Docs update checklist

- [ ] Соответствующие `docs/*.md` обновлены.
- [ ] Ссылки внутри docs валидны (не битые).
- [ ] Никаких placeholder-секций / TODO.
- [ ] Русский язык в новых разделах.
- [ ] ADR создан, если изменение архитектурное.

### 31.20 CI/CD checklist

- [ ] Workflow проходит на feature branch.
- [ ] Cache `composer`/`npm` настроен.
- [ ] Все critical checks обязательные (composer/phpstan/cs-fixer/phpunit/npm build).
- [ ] Secrets через GitHub Secrets.
- [ ] Deploy job требует успешных тестов.
- [ ] Notifications настроены.

### 31.21 Release readiness checklist

- [ ] CI зелёный.
- [ ] Migrations safe и протестированы на staging.
- [ ] Rollback plan описан.
- [ ] Бэкап выполнен.
- [ ] Smoke test список URL подготовлен.
- [ ] Команда уведомлена.
- [ ] Окно релиза согласовано.

---

## 32. Examples of safe change scope

### 32.1 P1. Новая публичная страница (через Page entity)

**Цель:** добавить `/about-company` с типом `content`.

- Через admin API `POST /admin/api/content/pages` создать `Page` (path/slug/title/h1/meta).
- Добавить блоки (`hero`, `text`, `seo_text`).
- Опубликовать через `POST /admin/api/content/pages/{id}/publish`.

**Кода не требуется** — редакторская задача. Если нужен новый тип блока — см. P10.

**Scope:** 0 файлов кода / БД-запись через admin API.

### 32.2 P2. Новый admin CRUD endpoint

**Цель:** CRUD для `Menu`.

**Файлы (нормальный scope):**

- `src/Module/Menu/Domain/Entity/Menu.php`
- `src/Module/Menu/Domain/Repository/MenuRepositoryInterface.php`
- `src/Module/Menu/Infrastructure/Repository/DoctrineMenuRepository.php`
- `src/Module/Menu/Application/Command/{Create,Update,Delete}MenuCommand.php`
- `src/Module/Menu/Application/Handler/{Create,Update,Delete}MenuHandler.php`
- `src/Module/Menu/Application/DTO/MenuOutput.php`
- `src/Module/Menu/UI/Admin/MenuApiController.php`
- `migrations/Version<...>.php`
- `config/services.yaml` — alias `MenuRepositoryInterface`.

**Тесты:**

- `tests/Unit/Menu/Domain/Entity/MenuTest.php`
- `tests/Unit/Menu/Application/Handler/CreateMenuHandlerTest.php`
- `tests/Integration/Menu/DoctrineMenuRepositoryTest.php`
- `tests/Functional/Menu/AdminMenuApiTest.php`

**Документация:**

- `src/Module/Menu/README.md`
- [05-domain-model](05-domain-model.md) — добавить раздел.
- [MODULES.md](legacy/MODULES.md) — статус модуля.

**Подозрительно широкий scope:** добавление Twig-шаблонов в Front-зоне, изменение sitemap, правка assets. Это другие задачи.

**Common mistakes:** забыли voter / `#[IsGranted]`; забыли alias репозитория; DTO принимает Doctrine entity.

### 32.3 P3. Новый use case без CRUD

**Цель:** `RestorePage`.

**Файлы:**

- `Application/Command/RestorePageCommand.php`
- `Application/Handler/RestorePageHandler.php`
- `Domain/Entity/Page.php` — добавить метод `restore()`.
- `UI/Admin/PageApiController.php` — `POST /admin/api/content/pages/{id}/restore`.

**Тесты:**

- `tests/Unit/Content/PageTest.php` — `restore()`.
- `tests/Unit/Content/Application/Handler/RestorePageHandlerTest.php`.
- `tests/Functional/Content/AdminContentApiTest.php` — endpoint.

**Документация:**

- [CONTENT_ENGINE.md](legacy/CONTENT_ENGINE.md) — описать endpoint.

### 32.4 P4. Новая Entity

См. P2 + [10-domain-layer](10-domain-layer.md).

**Не забыть:**

- `services.yaml` исключает `src/Module/*/Domain/Entity/` из автозагрузки сервисов.
- Repository interface в `Domain/Repository`, реализация в `Infrastructure/Repository`.
- Soft delete — trait `HasSoftDelete`. Timestamps — trait `HasTimestamps` + `TimestampListener`.
- Миграция (см. [18-migrations](18-migrations.md)).

### 32.5 P5. Новое поле в БД

**Цель:** добавить `Page.meta_description`.

**Шаги:**

1. Добавить поле в Entity.
2. `make migration` или `bin/console doctrine:migrations:diff`.
3. Просмотреть миграцию глазами.
4. Если NOT NULL и таблица не пуста: nullable + default → backfill → NOT NULL отдельной миграцией.
5. Обновить admin DTO + handler.
6. Обновить output DTO.
7. Обновить tests.
8. Обновить шаблоны/SEO рендер при необходимости.

**Документация:** [05-domain-model](05-domain-model.md), [CONTENT_ENGINE.md](legacy/CONTENT_ENGINE.md).

### 32.6 P6. Новый API endpoint

См. [14-api-area](14-api-area.md), [Раздел 9](#9-playbook-adding-a-new-api-endpoint).

**Admin API:** `/admin/api/<module>/...` + DTO + Validator + `#[IsGranted]` + `ContentApiResponder` + functional test.

**Public API:** `/api/v1/<module>/...` + auth (token/JWT) + rate limit + output DTO без leak Doctrine + OpenAPI doc.

### 32.7 P7. Новый Symfony Console command

**Файл:** `src/Module/<X>/UI/Console/<Action>Command.php` или `src/Shared/UI/Console/...`.

```php
#[AsCommand(name: 'app:seo:audit', description: 'Run SEO audit')]
final class SeoAuditCommand extends Command
{
    public function __construct(private readonly SeoAuditService $audit) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->audit->run();
        $output->writeln('Issues: ' . count($report->issues));
        return $report->hasCritical() ? Command::FAILURE : Command::SUCCESS;
    }
}
```

**Тесты:** `tests/Functional/<X>/<Action>CommandTest.php` через `CommandTester`.

### 32.8 P8. Новый Messenger message + handler

См. [24-messenger-and-queues](24-messenger-and-queues.md), [Раздел 20](#20-playbook-messengerworker-change).

**Файлы:**

- `Application/Message/<Verb><Subject>Message.php` — `final readonly`.
- `Application/MessageHandler/<Verb><Subject>Handler.php` — `#[AsMessageHandler]`.
- `config/packages/messenger.yaml` — routing.

**Не забыть:** идемпотентность, только примитивы/VO в message, logging start/ok/fail, явная retry, functional test с `in-memory://`.

### 32.9 P9. Новый Twig component

**Файлы:**

- Шаблон partial: `templates/<area>/<component>.html.twig`.
- Twig extension при необходимости: `src/Module/<X>/UI/Twig/<Component>Extension.php`.
- View model: `src/Module/<X>/Application/View/<Component>View.php`.

**Не забыть:** auto-escape ON; никаких Doctrine вызовов в шаблоне; передавать готовый view model; `lint:twig` ok.

### 32.10 P10. Новый SEO field

**Цель:** добавить `Page.canonicalOverride`.

**Шаги:**

1. Поле в Entity.
2. Migration.
3. Admin DTO + handler.
4. Output DTO + admin response.
5. В `templates/base.html.twig` (или специальный partial) `<link rel="canonical">` использует override, если задан.
6. Sitemap controller учитывает.
7. Tests + functional на canonical.

**Документация:** [26-seo-architecture](26-seo-architecture.md), [SEO_GUIDE.md](legacy/SEO_GUIDE.md).

### 32.11 P11. Новый sitemap source

**Цель:** включить `Product` в sitemap.

- В `SitemapController` (или новом `SitemapSourceProviderInterface`) — добавить источник.
- `Module\Catalog\Infrastructure\Sitemap\ProductSitemapSource implements SitemapSourceInterface`.
- DI tag (`sitemap.source`).
- Кэшировать в `cache.seo`.

**Тесты:** functional на sitemap содержит products.

### 32.12 P12. Новый cache pool

См. [23-cache-and-redis](23-cache-and-redis.md), [Раздел 19](#19-playbook-cacheredis-change).

В `config/packages/cache.yaml`:

```yaml
cache.<name>:
    adapter: cache.app
    default_lifetime: <seconds>
```

**Тесты:** integration на hit/miss.

### 32.13 P13. Новый healthcheck

См. [29-healthchecks](29-healthchecks.md), [Раздел 22](#22-playbook-healthcheck-change).

- `App\Shared\UI\Http\HealthCheckController` — расширить или добавить `/health/ready`.
- Композиция `HealthCheckRunner` + `HealthCheckInterface[]`.
- Каждый check — `final` class implements `HealthCheckInterface`.

### 32.14 P14. Изменение deploy pipeline

См. [34-deployment](34-deployment.md), [35-cicd](35-cicd.md), [Раздел 26](#26-playbook-installdeploy-script-change).

- `.github/workflows/deploy.yml` — тестировать через `workflow_dispatch` на feature branch.
- `tools/deploy/*.sh` — прогон на staging.
- Идемпотентность.

### 32.15 P15. Изменение Docker config

См. [32-docker-architecture](32-docker-architecture.md), [Раздел 24](#24-playbook-docker--compose--local-environment-change).

- `docker-compose.yml` — все сервисы поднимаются после `make build && make up`.
- Не использовать в prod.
- Версии образов жёсткие (`postgres:18`, `redis:8-alpine`, `nginx:1.30.0-alpine`, `node:25.9.0-bookworm`, `php:8.5-fpm-bookworm`).

### 32.16 P16. Новый модуль

См. [43-module-development-guide](43-module-development-guide.md).

### 32.17 P17. Новая интеграция

**Цель:** отправка через email-провайдер X.

- Контракт: `App\Module\<X>\Application\<Capability>Interface`.
- Реализация: `App\Module\<X>\Infrastructure\Integration\<Provider><Capability>` через `HttpClientInterface`.
- Конфиг env (DSN/токен).
- Logging канал `business`/`integration`.
- Timeout, retry, idempotency.
- Unit + integration test.
- `docs/integrations/<provider>.md` (целевая папка).

### 32.18 Что считать «подозрительно широким scope»

| Тип задачи | Подозрительный признак |
|------------|------------------------|
| Bugfix | Diff > 100 строк или > 5 файлов |
| Новое поле в БД | Меняется > 3 модулей |
| Новый endpoint | Меняется layout / sitemap |
| Refactor | Меняется поведение (нет тестов до = после) |
| Deploy script | Меняется Nginx + PHP-FPM + миграция в одном PR |
| Migration | Drop колонки + рефакторинг кода |

---

## 33. Definition of done

Изменение **завершено**, когда все пункты ниже выполнены.

- [ ] Code complete.
- [ ] Архитектура соблюдена ([04-layer-rules](04-layer-rules.md), [06-module-architecture](06-module-architecture.md)).
- [ ] Layer boundaries не нарушены.
- [ ] Public URL'ы защищены (или 301-редирект на новые).
- [ ] SEO impact проверен и зафиксирован.
- [ ] Тесты обновлены и зелёные ([31-testing-strategy](31-testing-strategy.md)).
- [ ] Docs обновлены (включая [28. What must be updated together](#28-what-must-be-updated-together)).
- [ ] Logging добавлен / обновлён.
- [ ] Config / env обновлены ([27-config-and-env](27-config-and-env.md)).
- [ ] Migrations safe и rollback продуман.
- [ ] Cache invalidation учтена.
- [ ] Uploads / files impact учтён.
- [ ] Deploy impact проверен ([34-deployment](34-deployment.md)).
- [ ] Rollback продуман и документирован.
- [ ] Нет unrelated edits.
- [ ] Нет security регрессий.
- [ ] Нет operational регрессий.
- [ ] Нет placeholder docs / TODO.
- [ ] CI gates могут пройти ([35-cicd](35-cicd.md), [38-coding-standards](38-coding-standards.md)).
- [ ] Release readiness checklist может пройти ([Раздел 31.21](#3121-release-readiness-checklist)).

Связанные документы: [31-testing-strategy](31-testing-strategy.md), [38-coding-standards](38-coding-standards.md), [42-feature-development-guide](42-feature-development-guide.md).

---

## 34. Quick reference

> Памятка на каждый день. 15 правил.

1. **Сначала классифицируй задачу — потом меняй код.** [Раздел 4](#4-task-classification-matrix).
2. **Минимальный scope.** Если можно убрать половину diff — убери.
3. **Не клади бизнес-логику в controller / Twig / repository.** Application и Domain — на своих местах.
4. **Не меняй публичный URL без 301 и SEO-проверки.** Поисковик не знает о твоём рефакторинге.
5. **Любое изменение БД требует migration reasoning** (additive vs mutating, backfill, NOT NULL после backfill).
6. **Любая новая env var требует обновления `.env*`, deploy templates, README, CI secrets, docs.**
7. **Любое изменение cache требует invalidation strategy.** TTL без инвалидации — стейл-бомба.
8. **Любое изменение worker требует retry/idempotency reasoning.**
9. **Любое изменение uploads требует security и backup reasoning.**
10. **Любой deploy / install скрипт идемпотентен.** `set -euo pipefail` + проверки.
11. **Никогда не возвращай Doctrine entity из API.** Только DTO.
12. **Никогда не пиши `doctrine:schema:update --force`.** Только миграции.
13. **Любой AI-агент делает impact map и planning note до реализации** (если задача не bugfix-1-line).
14. **Документация обновляется в одном PR с кодом.** Не «потом».
15. **Нет тестов и docs — изменение не завершено.** Definition of done — не декларация.

---

> **Конец playbook.**
>
> Связанные обязательные документы для ежедневной работы:
> [00-overview](00-overview.md) ·
> [02-architecture](02-architecture.md) ·
> [04-layer-rules](04-layer-rules.md) ·
> [16-routing](16-routing.md) ·
> [18-migrations](18-migrations.md) ·
> [26-seo-architecture](26-seo-architecture.md) ·
> [34-deployment](34-deployment.md) ·
> [38-coding-standards](38-coding-standards.md) ·
> [39-agent-guide](39-agent-guide.md) ·
> [40-cursor-rules](40-cursor-rules.md) ·
> [42-feature-development-guide](42-feature-development-guide.md) ·
> [43-module-development-guide](43-module-development-guide.md).
