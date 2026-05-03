# 40. Cursor Rules

> Этот документ — **строгие операционные правила для Cursor** (и любого другого AI coding agent),
> работающего в репозитории `zaborprofil`.
>
> Здесь нет «рекомендаций». Это **операционная процедура**: что именно делать, в каком порядке,
> что прочитать, что нельзя трогать, как минимизировать diff и как завершать задачу.
>
> Дополняет `docs/39-agent-guide.md` (mental model и обоснование), но не дублирует его.
> При конфликте — `39` объясняет «почему», `40` определяет «как».

---

## 1. Mission of Cursor in this project

Cursor существует, чтобы **помогать реализовывать изменения** в Symfony CMS Engine,
а не «пересобирать мир».

- Cursor не разрушает архитектуру.
- Cursor консервативен в изменениях.
- Cursor уважает boundaries слоёв и модулей.
- Cursor минимизирует diff.
- Cursor защищает SEO, миграции, деплой и безопасность.
- Cursor не превращает Symfony-проект в хаотичный набор сервисов.
- Cursor читает документацию **до** изменений, не после.
- Cursor не делает silent architectural decisions.

> **Базовое правило:** делай меньше, чем кажется нужным; читай больше, чем кажется необходимо.

---

## 2. Mandatory startup protocol before any code change

**До любого изменения** Cursor обязан выполнить шаги в этом порядке.

### 2.1 Прочитать минимальный обязательный набор

1. `docs/00-overview.md`
2. `docs/01-product-purpose.md`
3. `docs/02-architecture.md`
4. `docs/03-project-structure.md`
5. `docs/04-layer-rules.md`
6. `docs/39-agent-guide.md`
7. `docs/40-cursor-rules.md` (этот документ)

### 2.2 Прочитать релевантные документы по типу изменения

| Тип изменения                       | Документы                                                                                                                                  |
|------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| Controller / HTTP / Routing         | `docs/07-request-flow.md`, `docs/08-controller-architecture.md`, `docs/16-routing.md`                                                      |
| Application / Domain / Infrastructure | `docs/09-application-layer.md`, `docs/10-domain-layer.md`, `docs/11-infrastructure-layer.md`, `docs/04-layer-rules.md`                    |
| Admin                               | `docs/12-admin-area.md`, `docs/20-security-and-access-control.md`                                                                          |
| Front / Twig / Assets               | `docs/13-front-area.md`, `docs/21-templates-and-twig.md`, `docs/22-frontend-assets.md`, `docs/26-seo-architecture.md`                      |
| API                                 | `docs/14-api-area.md`, `docs/20-security-and-access-control.md`, `docs/30-error-handling.md`                                               |
| Doctrine / Database / Migrations    | `docs/17-doctrine-and-database.md`, `docs/18-migrations.md`, `docs/05-domain-model.md`                                                     |
| Forms / DTO / Validation            | `docs/19-forms-dto-validation.md`, `docs/09-application-layer.md`                                                                          |
| Security                            | `docs/20-security-and-access-control.md`, `docs/27-config-and-env.md`, `docs/30-error-handling.md`                                         |
| SEO                                 | `docs/26-seo-architecture.md`, `docs/13-front-area.md`, `docs/16-routing.md`, `docs/21-templates-and-twig.md`                              |
| Cache / Redis                       | `docs/23-cache-and-redis.md`, `docs/11-infrastructure-layer.md`                                                                            |
| Messenger / Workers                 | `docs/24-messenger-and-queues.md`, `docs/07-request-flow.md`, `docs/09-application-layer.md`                                               |
| Files / Uploads                     | `docs/25-files-and-uploads.md`, `docs/20-security-and-access-control.md`, `docs/36-backup-restore.md`                                      |
| Config / Env                        | `docs/27-config-and-env.md`, `docs/33-local-development.md`, `docs/34-deployment.md`                                                       |
| Logging / Errors / Healthchecks     | `docs/28-logging-observability.md`, `docs/29-healthchecks.md`, `docs/30-error-handling.md`, `docs/37-runbooks.md`                          |
| Tests / Quality                     | `docs/31-testing-strategy.md`, `docs/38-coding-standards.md`                                                                                |
| Docker / Local                      | `docs/32-docker-architecture.md`, `docs/33-local-development.md`                                                                            |
| Deploy / CI/CD / Backups            | `docs/34-deployment.md`, `docs/35-cicd.md`, `docs/36-backup-restore.md`, `docs/37-runbooks.md`                                              |
| Feature / Module development        | `docs/41-implementation-playbook.md`, `docs/42-feature-development-guide.md`, `docs/43-module-development-guide.md`, `docs/45-roadmap-and-extension-points.md` |

### 2.3 Прочитать сам код

- Файлы, которые планируется изменить — **полностью** (не угадывать структуру).
- Тесты рядом с этим кодом (`tests/...`).
- Связанные миграции, если меняется БД.
- `.env`, `.env.example`, конфиги в `config/packages/*`, если меняется конфигурация.
- `deploy/`, systemd unit'ы, runbooks, если изменение влияет на production.
- Routing + sitemap + redirects, если меняются URL/templates/metadata.
- Voters/firewall, если меняется auth/forms/uploads/admin actions.

### 2.4 Documentation gap

Если нужный документ **отсутствует** или **противоречит** коду:

- Cursor явно отмечает gap в плане работы.
- Cursor **не выдумывает** несуществующую архитектуру.
- Cursor предлагает создать/обновить документ как часть задачи или как follow-up.

---

## 3. Mandatory planning protocol

Перед написанием кода Cursor обязан составить план. Без плана — не писать код.

### 3.1 Шаблон плана

```
## Goal
<одно предложение, что делаем и зачем>

## Task class
<новая фича / багфикс / миграция БД / SEO / API / admin / deploy / refactor / docs / ...>

## Affected files
- src/Module/X/Domain/...
- src/Module/X/Application/...
- src/Module/X/Infrastructure/Doctrine/...
- src/Module/X/UI/Http/Controller/...
- templates/...
- migrations/Version<UTC>.php
- tests/...
- docs/...

## Untouched layers
- <слои и модули, которые НЕ должны измениться>

## Risks
- <риск 1>
- <риск 2>

## Verification after change
- [ ] PHPStan
- [ ] PHPUnit
- [ ] Manual smoke test on <url/console>
- [ ] Migration dry-run
- [ ] <др.>

## Migrations / docs / tests / config
- Migration: <yes/no, expand-contract?>
- Docs to update: <list>
- Tests to add: <list>
- Config/env changes: <yes/no>

## Impact
- SEO: <yes/no, что именно>
- Security: <yes/no, что именно>
- Deploy: <yes/no, что именно>
- Cache: <yes/no, что именно>
- Workers/Messenger: <yes/no, что именно>
```

### 3.2 Без плана — нет кода

- Если задача нетривиальная (3+ файла или класс изменения «фича/миграция/SEO/deploy/security/api») —
  показать план пользователю **до** изменений.
- Если задача тривиальная (1-2 файла, очевидное действие) — план может быть коротким, но классификация
  и список файлов обязательны.

---

## 4. File modification discipline

**Жёсткие правила.**

- Не изменять unrelated files. Никогда.
- Не запускать массовое форматирование, не сортировать импорты «попутно».
- Не переименовывать модули, классы, методы без явного запроса.
- Не менять `deploy/`, `config/packages/*`, Nginx, systemd unit'ы без проверки последствий.
- Не двигать границы слоёв без явного решения и/или ADR.
- Не менять публичный API без reasoning в плане.
- Не менять URL/routing без SEO reasoning.
- Не редактировать уже применённые миграции — создавать новую.
- Не добавлять composer/npm зависимости без явного обоснования и упоминания в плане.
- Не делать «улучшения по пути» — записывать их в follow-up.

> **Cursor heuristic:** если файл не упомянут в `## Affected files` — его нельзя трогать.

---

## 5. Architecture preservation rules

**Прямые запреты.**

- Не класть бизнес-логику в controllers.
- Не тащить Doctrine `EntityManager`, Symfony `Request`, Redis, Nginx-знание в Domain.
- Не вызывать `shell_exec`/`exec`/`system`/`proc_open` из произвольных мест.
- Не делать direct infrastructure calls из Domain (`file_get_contents`, `curl_*`, `\Redis`).
- Не обходить Application/use case слой ради «упростить контроллер».
- Не смешивать Twig presentation logic с domain logic (никаких вычислений правил в шаблоне).
- Не писать огромные god-functions.
- Не делать god services (`PageService` на 30 методов).
- Не отдавать Doctrine entities напрямую из API.
- Не смешивать admin / frontend / API логику в одном контроллере или сервисе.
- Не создавать circular dependencies между модулями.
- Не нарушать направление зависимостей (`Domain` ← `Application` ← `Infrastructure`/`UI`).
- Не импортировать `App\Module\X\*` из `App\Shared\*` (shared не знает модулей).
- Не импортировать `App\Module\X\Domain\Entity` из `App\Module\Y` напрямую — через published контракт модуля X.

---

## 6. Rules for making small changes

**Маленькая задача = минимальный diff.**

- Меняешь одну строку — меняй одну строку.
- Не «улучшать» соседние методы.
- Не добавлять «полезные» утилиты, которые не нужны для задачи.
- Не рефакторить класс «потому что коду 2 года».
- Сначала **закрыть** точечную задачу.
- Cleanup (если он нужен) — отдельный шаг с явной отметкой и **отдельным коммитом/PR**.
- Не менять архитектуру ради маленького исправления.

### 6.1 Шаблон small-change

```
1. Понял конкретное место.
2. Прочитал файл целиком + ближайшие тесты.
3. Минимальное изменение.
4. Прогнал тесты.
5. Проверил, что diff содержит только нужные строки.
6. (Опционально) Записал follow-up cleanup.
```

---

## 7. Rules for making medium/large changes

**Средняя/большая задача = stage'd implementation.**

Порядок:

1. **Design note.** Что меняем, почему, какие альтернативы рассматривались.
2. **Impacted files map.** Список файлов с пометкой `add/modify/delete`.
3. **Staged implementation:**
   - сначала расширить контракт (interface, DTO, value object);
   - потом добавить реализацию (Domain → Application → Infrastructure);
   - потом подключить к UI / API / admin;
   - потом миграции / config / deploy;
   - потом tests;
   - потом docs;
   - потом final review.
4. **Final review against architecture rules** (`docs/04-layer-rules.md`, разделы 5 и 8 настоящего документа).

> Никогда не начинать с UI и «дотягивать» Domain под него — путь к утечке инфраструктуры.

---

## 8. Mandatory checklists before commit-ready state

### 8.1 Pre-commit checklist

- [ ] Тесты обновлены или есть явное обоснование почему нет.
- [ ] Документация обновлена (таблица из раздела 21).
- [ ] Логирование добавлено где нужно.
- [ ] Config/env обновлён (`.env.example`, `docs/27`) если применимо.
- [ ] Migration safety учтён (раздел 9).
- [ ] SEO impact учтён (раздел 14).
- [ ] Cache impact учтён (раздел 17).
- [ ] Queue/worker impact учтён (раздел 18).
- [ ] Deployment impact учтён (раздел 10).
- [ ] Нет новых секретов в коде, шаблонах, миграциях, логах.
- [ ] Нет открытых наружу dangerous portов в compose/Nginx.
- [ ] Нет unrelated files в diff.
- [ ] Нет broad formatting changes.
- [ ] Нет accidental route changes.
- [ ] Нет accidental DB destructive changes.
- [ ] Нет hidden dependency (composer/npm) без обоснования.

---

## 9. Rules for migrations

### 9.1 Жёсткие правила

- Schema changes требуют Doctrine migration. Никаких ручных DDL на production.
- Миграция явно описана (`getDescription()`).
- Avoid destructive changes без transition plan (expand/contract).
- Update entities/repositories/tests/docs **в том же PR**.
- Note deploy order, если миграция влияет на порядок релиза.
- **Не редактировать** уже применённые миграции — создавать новую.
- Проверять nullable/default/indexes/FK.
- Учитывать data migration impact (объём, время).
- Учитывать rollback expectations (`down()`).
- Учитывать production data safety (бэкап перед запуском destructive миграции).

### 9.2 Migration pre-flight checklist

- [ ] Прочитан `docs/18-migrations.md`.
- [ ] Сгенерирована через `bin/console doctrine:migrations:diff`, ручные правки минимальны.
- [ ] `up()` корректен, `down()` определён или явно объяснено почему отсутствует.
- [ ] Нет импортов `App\Module\*\Domain\*` в файле миграции.
- [ ] Все FK снабжены индексом.
- [ ] Все NOT NULL имеют default или transitional план.
- [ ] Учтены индексы под новые WHERE/ORDER BY.
- [ ] Прогнана локально: `migrate --dry-run`, потом `migrate`, потом `migrate prev` (если down есть).
- [ ] Учтены workers/jobs, читающие изменяемую таблицу.
- [ ] Учтена обратная совместимость с уже стоящими в очереди сообщениями.
- [ ] Обновлены docs (`docs/05`, `docs/17`, `docs/18`).
- [ ] Обновлены fixtures и тесты.

> **Don't:** редактировать `Version20260101120000.php`, который уже применился на staging/prod.
> **Do:** создать новую `Version20260103090000.php`, исправляющую/дополняющую предыдущую.

---

## 10. Rules for deploy/config changes

| Изменение                  | Что обязательно                                                                                                            |
|----------------------------|----------------------------------------------------------------------------------------------------------------------------|
| Dockerfile                 | объяснить, какие пакеты/расширения добавлены и зачем; не ломать локальную сборку                                            |
| docker-compose             | service impact reasoning (что зависит, кто стартует первым); не менять published ports без причины                         |
| Nginx                      | request flow reasoning (что меняется в `try_files`/`location`/`return`/`rewrite`); проверка SEO redirects                  |
| PHP-FPM                    | runtime reasoning (memory_limit, opcache, pm.\*); проверка влияния на производительность                                    |
| certbot/SSL                | renewal reasoning (cron/timer, периодичность, fallback)                                                                     |
| install/deploy скрипты     | сохранение идемпотентности (`set -euo pipefail`, проверки состояния)                                                       |
| env переменная             | `.env.example` + `docs/27-config-and-env.md` + `docs/DEPLOY_VARIABLES.md` + README (если влияет на запуск)                 |
| GitHub Actions             | объяснить CI impact (какие job'ы добавлены, что блокируют, какие secrets нужны)                                            |
| systemd / worker unit      | объяснить restart/failure behavior (`Restart=`, `RestartSec=`, `OOMScoreAdjust=`)                                          |

### 10.1 Запреты

- Не выкатывать env переменную в код без записи в `.env.example`.
- Не менять `Restart=always` на `Restart=no` без обоснования.
- Не открывать порты Redis/Postgres наружу.
- Не отключать security headers в Nginx «попутно».
- Не упрощать deploy скрипт за счёт идемпотентности.

---

## 11. Rules for Symfony code

- Контроллеры — **тонкие**. Получили DTO, вызвали handler, вернули Response.
- Сервисы имеют **одну ответственность**, размером с одну страницу A4 в идеале.
- DI через autowiring (constructor). Не злоупотреблять `ContainerInterface` (service locator).
- DTO — для входов от пользователя. Без public mutable полей без необходимости.
- Validator — для валидации, не для бизнес-сценария.
- Forms / DTO / Application / Domain — четыре разные роли. Не смешивать.
- EventSubscriber/Listener — для **инфраструктурных** задач (см. `docs/04-layer-rules.md`).
- Бизнес-правила **никогда** в Doctrine lifecycle callbacks.
- Никаких тяжёлых вычислений в setter/getter Entity.
- Lazy loading — осознанно, проверять N+1 (`fetch="EAGER"` где это явно нужно, `JOIN` в Repository).
- Транзакции — на уровне Application use case, не в контроллере, не в Repository, не в Entity.

---

## 12. Rules for Doctrine and repositories

- Repositories **запрашивают** данные. Не выполняют бизнес-сценарии.
- `QueryBuilder` не утекает за пределы Doctrine Repository.
- Никаких `findAll()` без пагинации в production-коде.
- Никаких `EntityRepository`-наследников, отдающих `QueryBuilder` наружу.
- Никаких хаотичных `SELECT` через `Connection` в Application.
- Mapping и миграции обновляются вместе.
- Никаких infrastructure импортов в Domain entity.
- Cascade `remove`, `orphanRemoval` — осознанно, не «по умолчанию».
- Nullable/unique constraints — указывать явно в attributes и в миграции.
- Lifecycle callbacks — только технические (timestamps, search index update — лучше через listener).

---

## 13. Rules for Twig/frontend changes

- Twig — слой представления. Никаких Doctrine queries, никаких бизнес-вычислений.
- Шаблон получает **подготовленный** view model.
- Реиспользуемые блоки — через `include`/`embed`/`macro` в `templates/_partials/*`.
- SEO теги (title, description, canonical, og:\*, JSON-LD, robots) — не удалять и не менять формат «по пути».
- Не ломать canonical, breadcrumbs, h1.
- Не вводить layout shift (CLS) — резервировать размеры для изображений и асинхронных блоков.
- Не удалять базовую a11y (alt, label, focus, ARIA).
- Frontend assets — Vite build должен оставаться рабочим.
- Critical pages (главная, посадочные SEO, каталог, страницы услуг) — проверяются вручную после изменений.
- Mobile / responsive — проверяется хотя бы на breakpoints из `tailwind.config.js`.

---

## 14. Rules for SEO-sensitive changes

**Don't:**

- Менять URL casually.
- Менять route без 301-редиректа со старого URL.
- Менять slug existing страницы без redirect/canonical reasoning.
- Менять title / description / h1 на критичных страницах без SEO reasoning.
- Менять sitemap.xml без валидации (XML, лимиты).
- Менять robots.txt без явного намерения.
- Включать `noindex`/`nofollow` на индексируемые страницы.
- Удалять/менять breadcrumbs без проверки JSON-LD.
- Менять structured data без re-валидации (schema.org).

**Do:**

- На каждом route change задавать вопрос: «Где 301?».
- Учитывать pagination/filter pages (rel=next/prev устарели, но canonical обязателен).
- Сохранять hreflang при изменении локали/доменной структуры.
- Документировать SEO impact в PR description.

---

## 15. Rules for admin/CMS changes

- Admin actions требуют авторизации (voter / `is_granted`).
- Admin forms валидируются (Validator на DTO).
- Admin changes не должны утекать на frontend неожиданно (например, новое поле, появившееся на публичной странице без согласования).
- CRUD изменения требуют обновления docs (`docs/12-admin-area.md`, `docs/ADMIN_GUIDE.md` или `docs/CONTENT_EDITOR_GUIDE.md`).
- Content model changes — обычно требуют миграции.
- SEO поля сущностей (title, description, slug, canonical, og:image, noindex) **сохраняются** при любых рефакторингах.
- Destructive admin actions — confirmation + audit log.
- Audit / logging для критичных действий обязателен (publish/unpublish, delete, restore, role change).

---

## 16. Rules for API changes

- Не отдавать internal entities напрямую. Использовать DTO/normalizer/resource.
- Validate input через DTO + Validator constraints.
- Сохранять backward compatibility, где возможно.
- Документировать response changes в `docs/14-api-area.md`.
- Tests обязательны на error responses (4xx, 5xx).
- Не утекать stack trace в response.
- Не отдавать internal IDs (Doctrine internal sequences, hash) если не intended.
- Auth / permissions — explicit, через voter / `is_granted`, не через «если роль admin».
- Pagination — обязательна для list endpoints.

---

## 17. Rules for cache/Redis code

- Cache keys предсказуемы (`<module>.<entity>.<id>` / `<module>.<query>.<hash>`).
- Версионирование ключа (`v1`, `v2`) при изменении формата.
- Invalidation спроектирована **до** добавления cache.
- Не кэшировать секреты, токены, пароли.
- Не кэшировать private user data в shared pool.
- Stale data risk описан в комментарии или docs (`docs/23-cache-and-redis.md`).
- Redis outage не должен крашить flow, который может работать без cache (graceful fallback).
- Cache behavior упомянут в `docs/23` для нетривиальных случаев.
- Tests на cache-sensitive логику где возможно (write → read → invalidate → read).

---

## 18. Rules for Messenger/worker code

- Handler **идемпотентен** (или явно документирует отсутствие идемпотентности).
- Job/status переходы — explicit (state machine на Domain уровне, если применимо).
- Retries ограничены (`max_retries` на transport).
- Дубликаты обрабатываются (dedup через `message_id` или через состояние сущности).
- Partial completion обрабатывается (сохраняется прогресс).
- Failures логируются с `job_id`, `entity_id`, `user_id`, `correlation_id`.
- Long-running tasks учитывают timeout и memory.
- Бизнес-логика делегируется Application service, handler — тонкий.
- Messages — стабильные, версионируемые DTO.
- Schema changes (БД, DTO сообщений) учитывают **уже стоящие в очереди** сообщения.
  Если несовместимо — drain очереди в runbook.

---

## 19. Logging rules for Cursor-authored code

- Использовать `LoggerInterface` через DI, не `error_log` / `var_dump` / `dump`.
- Каналы — по назначению (`security`, `messenger`, `app`, `deprecation`).
- Контекст — `request_id`, `user_id`, `entity_id`, `job_id`, `correlation_id` где применимо.
- Не логировать: пароли, токены, JWT, API ключи, cookies, секреты, полные тела с PII.
- Error logs сохраняют trace и контекст.
- User-facing errors отделены от internal logs (пользователю — message + `request_id`, лог — детали).
- Не «шумные» логи: не логировать каждый успешный read.
- Security/admin/deploy/worker failures — обязательно логируются с контекстом.
- Достаточно деталей, чтобы дебажить production без `var_dump`.

---

## 20. Security rules

- **Никогда** не вводить unsafe shell execution без airtight justification (и без code review).
- Validate URLs (формат, схема, host allow-list, no internal IPs).
- Защита от path traversal (allow-list директорий, `Path::join`, `realpath` + проверка префикса).
- Не выставлять internal services наружу (Redis, Postgres, MailHog, MinIO bind 127.0.0.1).
- Не утекать temp file paths в response/log.
- Не хранить secrets в коде.
- Не ослаблять Nginx/firewall defaults.
- Validate uploads (MIME sniff, расширение, размер, изолированное хранение, antivirus если применимо).
- CSRF проверки на формы и admin API.
- Access control через voters / `is_granted`, не inline.
- Role permissions проверяются explicit.
- Не утекать stack trace в production response.
- Не логировать tokens / passwords / API keys / cookies / Authorization headers.
- Env secrets вне git (`.gitignore`, secret scanning на CI).
- HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy в Nginx.

---

## 21. Documentation update rules

| Тип изменения                                | Обязательное обновление документации                                                                  |
|---------------------------------------------|-------------------------------------------------------------------------------------------------------|
| Архитектурное решение                       | `docs/02-architecture.md` + новый `docs/adr/000X-...md`                                               |
| Layer rules                                 | `docs/04-layer-rules.md`                                                                              |
| Module structure                            | `docs/06-module-architecture.md`, `docs/43-module-development-guide.md`                               |
| Request flow                                | `docs/07-request-flow.md`                                                                             |
| Domain model                                | `docs/05-domain-model.md`, `docs/10-domain-layer.md`                                                  |
| Database schema                             | `docs/17-doctrine-and-database.md`, `docs/18-migrations.md`                                           |
| Provider/integration contract               | `docs/11-infrastructure-layer.md`                                                                     |
| Deployment process                          | `docs/34-deployment.md`, `docs/37-runbooks.md`, `docs/35-cicd.md`                                     |
| Env / config                                | `docs/27-config-and-env.md`, `.env.example`, `docs/DEPLOY_VARIABLES.md`                               |
| Install scripts                             | `docs/INSTALL.md`, `docs/33-local-development.md`                                                      |
| Backup / cleanup behavior                   | `docs/36-backup-restore.md`                                                                           |
| Runbooks                                    | `docs/37-runbooks.md`                                                                                 |
| SEO behavior                                | `docs/26-seo-architecture.md`                                                                         |
| Admin behavior                              | `docs/12-admin-area.md`, `docs/ADMIN_GUIDE.md`                                                        |
| API behavior                                | `docs/14-api-area.md`, `docs/30-error-handling.md`                                                    |
| Queue/worker behavior                       | `docs/24-messenger-and-queues.md`, `docs/37-runbooks.md`                                              |
| Cache behavior                              | `docs/23-cache-and-redis.md`                                                                          |
| Files / uploads                             | `docs/25-files-and-uploads.md`, `docs/UPLOAD_SECURITY.md`                                             |
| Security / RBAC                             | `docs/20-security-and-access-control.md`, `docs/ROLES.md`                                             |
| Logging / observability                     | `docs/28-logging-observability.md`                                                                    |
| Healthcheck behavior                        | `docs/29-healthchecks.md`                                                                             |
| Coding standards                            | `docs/38-coding-standards.md`                                                                          |
| AI/Cursor rules                             | `docs/39-agent-guide.md`, `docs/40-cursor-rules.md`                                                   |
| Troubleshooting                             | `docs/44-troubleshooting.md`                                                                          |

> Если документ изменяется значимо — обновить ссылки на него в `docs/README.md`.

---

## 22. Anti-patterns specific to Cursor

**Запрещено.**

- **Broad rewrites** — переписывание модулей под предлогом «улучшения».
- **Speculative refactors** — рефакторинг «на будущее» без явной задачи.
- **Silent architecture drift** — изменение boundaries без ADR / docs.
- **20 файлов на 1 баг** — раздувание scope.
- **New dependency без необходимости** — добавление composer/npm пакета без plan'а.
- **Casual layer-jumping** — перенос логики между Domain/Application/Infrastructure без обоснования.
- **`// TODO:`** вместо завершения — либо сделать, либо вынести в отдельный issue / follow-up список.
- **Placeholder docs** — пустые секции с "TBD" без задачи на доработку.
- **Infra без runbook** — изменение `deploy/` / systemd / Nginx без обновления `docs/37`.
- **Queue semantics drift** — изменение DTO сообщений без миграции очереди.
- **Routes без SEO reasoning** — изменение URL без 301.
- **Casual migration edits** — правка применённых миграций.
- **Бизнес-логика в Twig.**
- **Контроллеры → сервисы** — вытаскивать «логику» из контроллера в god service.
- **Сервисы → god objects** — собирать всё в один класс.
- **Env без docs** — новая переменная без записи в `.env.example` и `docs/27`.
- **Unrelated formatting** — массовая правка пробелов/импортов «потому что IDE».
- **Blindly framework defaults** — следование default'ам Symfony, когда архитектура проекта говорит иначе
  (например, использовать FormType вместо DTO+Validator — см. `docs/adr/0005`).

---

## 23. Cursor decision protocol when uncertain

Если непонятно, что делать:

1. **Stop broad modification.** Не расширять scope, чтобы «угадать».
2. **Narrow the scope.** Сузить задачу до самой маленькой осмысленной единицы.
3. **Document assumptions.** Записать предположения явно в ответе пользователю.
4. **Choose the safer implementation.** Из двух решений выбрать более консервативное.
5. **Mark follow-up work.** Что осталось — записать как follow-up, не делать silent.
6. **Не выдумывать** скрытую архитектуру.
7. **Не менять** boundaries silently.
8. **Не вводить** destructive миграцию.
9. **Не менять** публичный URL/API behavior без явного reasoning и подтверждения.
10. **Предложить варианты** (2–3 с trade-off), если решение архитектурное.
11. **Создать/обновить ADR**, если решение значимое.

---

## 24. Cursor completion protocol

Перед завершением задачи Cursor обязан:

1. Сравнить implementation с архитектурными правилами (раздел 5, `docs/04-layer-rules.md`).
2. Просмотреть diff на unrelated edits и broad formatting.
3. Проверить docs / tests / config обновления.
4. Проверить migrations (raздел 9).
5. Проверить SEO impact (раздел 14).
6. Проверить security impact (раздел 20).
7. Проверить cache impact (раздел 17).
8. Проверить deploy impact (раздел 10).
9. Убедиться, что нет новых secrets в коде / шаблонах / миграциях.
10. Убедиться, что нет регрессий security defaults.
11. Убедиться, что нет регрессий deploy defaults.
12. Проверить наличие error handling / logging там, где нужно.
13. Запустить локально (где применимо):
    - `composer validate --strict`
    - `composer check:syntax`
    - `vendor/bin/php-cs-fixer fix --dry-run --diff`
    - `vendor/bin/phpstan analyse`
    - `vendor/bin/rector process --dry-run`
    - `vendor/bin/phpunit`
    - `npm run build`
14. Сформировать summary для пользователя:
    - что изменено и почему;
    - какие файлы затронуты;
    - какие tests/docs/migrations/config обновлены;
    - какие импакты учтены (SEO/security/deploy/cache/queues);
    - что осталось как follow-up.

---

## 25. Strong final checklist

Финальный hard checklist в стиле operating procedure. **Ни один пункт не пропускается.**

- [ ] **Scope confirmed** — задача классифицирована, цель сформулирована.
- [ ] **Affected files reviewed** — все изменённые файлы прочитаны до правки.
- [ ] **Boundaries respected** — domain/application/infrastructure/UI не нарушены.
- [ ] **No unrelated files changed** — diff содержит только то, что относится к задаче.
- [ ] **Tests updated or reason documented** — обновлены или обоснованно нет.
- [ ] **Docs updated or reason documented** — обновлены или обоснованно нет.
- [ ] **Migrations generated/checked if needed** — по правилам раздела 9.
- [ ] **Env/config docs updated** — если меняли env/config.
- [ ] **SEO checked** — для всего, что может влиять (раздел 14).
- [ ] **Security checked** — для всего, что может влиять (раздел 20).
- [ ] **Logging checked** — добавлено где нужно, без секретов.
- [ ] **Deploy checked** — `deploy/`/systemd/Nginx не сломаны.
- [ ] **Rollback / migration risk considered** — план отката понятен.
- [ ] **Final diff reviewed** — глазами, перед сдачей.
- [ ] **No hidden assumptions left undocumented** — всё записано.
- [ ] **Локальные проверки прошли** (composer, phpstan, phpunit, rector dry-run, php-cs-fixer dry-run, npm run build — где применимо).

---

## 26. 10 golden rules for Cursor in this project

1. **Сначала читать docs, потом код, потом править.**
2. **Минимальный diff. Никаких unrelated правок.**
3. **Domain не знает про Symfony, Doctrine, Redis, HTTP, Twig.**
4. **Контроллеры тонкие. Бизнес-логика в Application.**
5. **Doctrine entities не отдаются наружу — только DTO/view models.**
6. **URL/route/slug/canonical не меняем без 301 и SEO reasoning.**
7. **Применённые миграции не редактируем — создаём новую.**
8. **Новая env переменная = `.env.example` + docs/27 + deploy templates.**
9. **Tests + docs обновляются в том же PR, не «потом».**
10. **Если непонятно — уменьшаем scope, спрашиваем, не угадываем.**

---

> **Помни:** в этом проекте Cursor — не «писатель кода», а **дисциплинированный исполнитель**.
> Маленький честный diff, пройденный по checklist, ценнее любого большого «улучшения».
