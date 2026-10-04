# 45. Roadmap и extension points

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
- порядок установки и запуска описан в [34-deployment](34-deployment.md) и [37-runbooks](37-runbooks.md).

Остаётся операционно на VPS/GitHub:

- выполнить первый staging smoke deploy;
- завести реальные GitHub Environment secrets для staging/production;
- провести restore rehearsal на реальном backup;
- установить monitoring timer и logrotate на VPS.

### Исторические волны W0-W11 (закрыты)

- W0 Foundations, W1 Security baseline, W2 Logging, W3 Health and diagnostics.
- W4 Maintenance/Audit/Business events, W5 DevOps safety.
- W6 SEO core, W7 SEO audit/pre-publish checklist, W8 Cache invalidation/preview links.
- W9 Media, W9.1 Menu, W10 Leads, W11 Dev/QA and docs.

Эти волны считаются завершенным baseline. Новая разработка ведётся поверх текущих extension points.

### Этап 2 — Catalog

Готово:

- Catalog core: `Category`, `Product`, `Variant`;
- Admin API для категорий, товаров и вариантов;
- базовая миграция и permissions `catalog.view` / `catalog.manage`.
- публичный SSR каталога, категорий и карточек товара;
- SEO metadata / canonical / JSON-LD `Product`;
- включение опубликованных indexable товаров в sitemap.

### Позже — Commerce / Customer / API

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
- Search (MySQL FULLTEXT InnoDB с ngram parser или внешний поиск: Meilisearch/OpenSearch/ElasticSearch).
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

## Известные баги

Ранее зафиксированные `AdminNoIndexHeaderTest` и 4 ошибки PHPStan устранены: тест проходит, `composer check:phpstan` чистый.

Открытая проблема, выявленная матрицей ролей (`tests/Functional/Security/AdminRoleMatrixTest.php`): `AdminUser::getRoles()` неявно добавляет `ROLE_ADMIN` любому пользователю, поэтому `ROLE_EDITOR`, `ROLE_SEO` и `ROLE_MANAGER` фактически являются администраторами. Проверки матрицы для этих ролей пропускаются (`skipped`) и включатся автоматически после правки (пункт A2 плана улучшений админки).

## Risks (фиксация для архитектурного контроля)

- Расхождение версий PHP/MySQL между Docker и VPS.
- Неконтролируемое разрастание `Application\Service`.
- Возможность утечки бизнес-логики в `EventSubscriber`.
- Слабая защита `/admin` от случайного открытия профайлера на prod.
- Отсутствие formal SLO/мониторинга в production.
- Manual content migration из WordPress — потенциально долгая.

## Связанные документы

- [00-overview](00-overview.md)
- [06-module-architecture](06-module-architecture.md)
- [42-feature-development-guide](42-feature-development-guide.md)
