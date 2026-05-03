# 45. Roadmap и extension points

См. также [ROADMAP.md](legacy/ROADMAP.md), [FEATURES_PLAN.md](legacy/FEATURES_PLAN.md).

## Roadmap по этапам

### Готово (фактическое)

- Symfony 8.1+ каркас (composer constraint `^8.1`).
- Auth + RBAC + AdminPermissionVoter.
- Content Engine: Page / PageBlock + admin API + публичный SSR.
- SEO base: redirects (`Redirect` entity + `RedirectKernelSubscriber` + `PagePathChangeListener`), sitemap index/chunks, robots manager, canonical guard, OpenGraph, JSON-LD, SEO audit.
- Settings + Twig extension + `SettingsService` с кэшированием через `cache.app`.
- Logging: каналы, processors (`PiiRedactor`, `RequestId`), Telegram critical через async Messenger (Doctrine transport).
- Healthcheck `/health`.
- Preview links для черновиков с `noindex,nofollow`.
- Public page cache с tag-aware invalidation.
- Media Library: безопасные upload/list/delete, image re-encode, WebP/AVIF variants.
- Menu: управляемые позиции `header`, `footer`, `service`, breadcrumbs, JSON-LD `BreadcrumbList`.
- Leads: публичная SSR-форма, anti-spam, consent snapshot, email/Telegram notifications.
- Dev/QA readiness: `make init`, расширенный `make quality`, `app:smoke:test`.
- CI/CD: lint + phpstan + rector + phpunit + npm build + deploy gate.
- Docker для local dev, native deploy для VPS.

### Этап 1 — release readiness (следующий)

Репозиторий подготовлен:

- staging deploy запускает `tools/deploy/staging-smoke.sh`;
- GitHub Actions использует environments `staging` и `production`;
- restore rehearsal автоматизирован через `tools/deploy/restore-rehearsal.sh`;
- monitoring/log rotation/alerting подготовлены через `tools/deploy/monitoring-check.sh` и templates в `tools/deploy/templates/`;
- порядок установки и запуска описан в [RELEASE_READINESS](RELEASE_READINESS.md).

Остаётся операционно на VPS/GitHub:

- выполнить первый staging smoke deploy;
- завести реальные GitHub Environment secrets для staging/production;
- провести restore rehearsal на реальном backup;
- установить monitoring timer и logrotate на VPS.

### Этап 2 — Catalog / Commerce

Частично готово:

- Catalog core: `Category`, `Product`, `Variant`;
- Admin API для категорий, товаров и вариантов;
- базовая миграция и permissions `catalog.view` / `catalog.manage`.

Дальше:

- публичный SSR каталога и карточки товара;
- sitemap source для опубликованных товаров;
- SEO metadata / JSON-LD `Product` на уровне товара;
- Корзина и заказ.
- Customer (B2C) с регистрацией.
- Public API v1.
- HTTP cache reverse proxy для публичных страниц.
- Domain events через Symfony EventDispatcher / Messenger.

### Этап 3

- Partner модуль (B2B).
- Платежная интеграция.
- Доставка / интеграции с логистикой.
- Полноценный AuditLog с UI.
- Search (PostgreSQL FTS или ElasticSearch).
- OpenAPI для public API.

### Этап 4 (отдалённое)

- Personalization / A/B на базе настройки.
- Multi-language (i18n).
- Multi-tenant / multi-domain.

## Extension points

Места, специально оставленные открытыми для расширения **без переписывания кода**:

| Extension point | Где | Как расширить |
|---|---|---|
| Block partial’ы | `templates/public/blocks/<type>.html.twig` | Создать новый partial, добавить enum case в `BlockType`, обновить admin UI |
| Sitemap источники | целевое: `SitemapSourceInterface[]` | Реализовать interface, тег `sitemap.source` |
| Healthcheck source | целевое: `HealthCheckInterface[]` | Реализовать interface, тег `app.healthcheck` |
| Role / Permission | `AdminPermission` enum + `AdminPermissionVoter` | Добавить case + map в voter |
| Domain event subscribers | целевое: Symfony EventDispatcher | `#[AsEventListener]` |
| Twig extensions | `App\*\UI\Twig\*Extension` | Новый AbstractExtension |
| Cache pools | `config/packages/cache.yaml` | Добавить пул |
| Messenger handlers | `Application/MessageHandler` | `#[AsMessageHandler]` |
| File storage | `FileStorageInterface` | Реализация `S3FileStorage` (целевое) |
| HTTP integrations | `Infrastructure/Integration/*` | HTTP client + interface |
| Console commands | `UI/Console` или `Shared/UI/Console` | `#[AsCommand]` |

## Внутренние improvement candidates (без отдельной фичи)

> AI-агенту: эти пункты — **не выполнять без отдельной задачи**. Они зафиксированы как «известные технические долги» и должны быть приоритезированы продакт-ответственным.

- Подключить `phpat` или `deptrac` для статической проверки границ слоёв.
- `tools/quality/docs-coverage.php` — линтер «доки vs код»: парсит все `docs/**/*.md`, извлекает упоминания классов (`App\\...`), путей (`src/...`, `config/...`, `migrations/...`, `templates/...`), команд `bin/console <cmd>`, env-переменных `APP_*` / `DATABASE_URL` / etc.; проверяет, что они существуют в репозитории. Запускается в CI как отдельный non-blocking job (целевое: blocking после стабилизации).
- Поднять PHPStan до level 9 (если не поднят).
- Добавить `phpunit/php-code-coverage` + порог покрытия в CI.
- Централизованный `App\Shared\UI\Http\JsonRequestParser` вместо локального `JsonRequest`.
- Вынести `AdminUser` в более «доменный» модуль или явно зафиксировать как persistence-only.
- Унифицировать ContentApiResponder в `App\Shared\UI\Http\JsonApiResponder`.
- Реализовать domain events bus.
- Добавить `ClockInterface` для тестируемости времени.
- Healthcheck readiness endpoint.
- Cache warmup CLI.
- Prometheus exporter / OpenTelemetry traces.
- Lighthouse в CI.
- Symfony Secrets Vault вместо `.env.production`.
- Deptrac/Pact unit для проверки публичных API между модулями.

## Известные баги (предсуществующие, не блокирующие)

- `AdminNoIndexHeaderTest::testPublicResponseDoesNotCarryXRobotsTagHeader` падает: `GET /` возвращает `X-Robots-Tag: noindex` (одиночное слово, не `noindex, nofollow, noarchive` от `AdminNoIndexSubscriber`). Источник пока не выявлен. Не связано с фичей SEO/Cache (подтверждено baseline-проверкой). Завести отдельный bugfix-тикет.
- PHPStan: 4 предсуществующих ошибки в `src/Shared/UI/Twig/ViteAssetExtension.php` (booleanNot.alwaysFalse, booleanAnd.rightAlwaysTrue) и `tests/Unit/Content/UI/Admin/ContentApiResponderTest.php` (assign.propertyType, cast.string). Не относятся к новым фичам.

## Risks (фиксация для архитектурного контроля)

- Расхождение версий PHP/Postgres/Redis между Docker и VPS.
- Неконтролируемое разрастание `Application\Service`.
- Возможность утечки бизнес-логики в `EventSubscriber`.
- Слабая защита `/admin` от случайного открытия профайлера на prod.
- Отсутствие formal SLO/мониторинга в production.
- Manual content migration из WordPress — потенциально долгая.

## Связанные документы

- [00-overview](00-overview.md)
- [06-module-architecture](06-module-architecture.md)
- [ROADMAP.md](legacy/ROADMAP.md)
- [FEATURES_PLAN.md](legacy/FEATURES_PLAN.md)
