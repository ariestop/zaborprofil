# 45. Roadmap и extension points

См. также [ROADMAP.md](legacy/ROADMAP.md), [FEATURES_PLAN.md](legacy/FEATURES_PLAN.md).

## Roadmap по этапам

### Готово (фактическое)

- Symfony 8.1+ каркас (composer constraint `^8.1`).
- Auth + RBAC + AdminPermissionVoter.
- Content Engine: Page / PageBlock + admin API + публичный SSR.
- SEO base: redirects (`Redirect` entity + `RedirectKernelSubscriber` + `PagePathChangeListener`), sitemap, robots.
- Settings + Twig extension + `SettingsService` с кэшированием через `cache.app`.
- Logging: каналы, processors (`PiiRedactor`, `RequestId`), Telegram critical через async Messenger (Doctrine transport).
- Healthcheck `/health`.
- CI/CD: lint + phpstan + rector + phpunit + npm build + deploy gate.
- Docker для local dev, native deploy для VPS.

### Этап 1 — следующий (целевое)

- **Расширение SEO-полей Page** ([ADR-0010](adr/0010-seo-first-cms-architecture.md), [26-seo-architecture.md](26-seo-architecture.md)): добавить в `Page` `metaDescription`, `canonicalOverride`, `ogTitle`, `ogDescription`, `ogImage`, `jsonLd` (или embedded `SeoMetadata`); пробросить из `PublicPageController` в Twig; учесть `Page.indexable` в `<meta name="robots">`. Functional-тесты на наличие canonical/description/robots.
- **Кэширование публичного рендера**: подключить `cache.public_page` к `PublicPageController` с инвалидацией в Content-handler'ах. Integration-тесты cache hit/miss.
- **Vue 3 admin SPA shell** ([ADR-0012](adr/0012-admin-shell-spa-pattern.md)): редуцировать `templates/admin/dashboard.html.twig` до shell-маунтинга; перенести CRUD страниц/блоков на Vue Router + JSON API.
- Media модуль: загрузки, image processing, варианты.
- Menu модуль: управляемые меню для разных позиций.
- Расширение SEO: `app:seo:audit`, JSON-LD, OpenGraph управление, breadcrumbs.
- Sitemap chunking.
- Audit log базовый (publish, delete, role change).
- Lead модуль с антиспамом и email-нотификациями.
- Расширение тестов: SEO checks, security smoke.

### Этап 2

- Catalog: Category, Product, Variant.
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
