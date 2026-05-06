# 03. Структура проекта

Этот документ — карта репозитория. Любая папка, упомянутая здесь, имеет назначение, разрешённое содержимое и явные запреты.

## Корневая структура

```text
zaborprofil/
├── .github/workflows/         # CI и Deploy workflows
├── .osp/                      # OSPanel project config
├── .trunk/                    # trunk.io linters
├── assets/
│   ├── admin/                 # React + TypeScript admin SPA entrypoint
│   └── site/                  # public site assets
├── bin/console                # Symfony console
├── config/                    # Symfony bundles & packages config
│   ├── packages/
│   ├── routes/
│   ├── routes.yaml
│   ├── services.yaml
│   └── bundles.php
├── docker/                    # Dockerfiles + nginx/postgres/php config (dev only)
├── docs/                      # Эта документация (русская)
├── migrations/                # Doctrine migrations
├── public_html/               # Web root (NOT public/)
│   ├── index.php
│   ├── build/                 # Vite build output
│   └── uploads/               # User uploads (через volume / shared dir)
├── src/
│   ├── Kernel.php
│   ├── Module/                # Bounded modules
│   └── Shared/                # Shared kernel
├── templates/                 # Twig templates
│   ├── admin/
│   ├── public/
│   └── base.html.twig
├── tests/                     # PHPUnit tests
│   ├── Unit/
│   ├── Integration/
│   ├── Functional/
│   ├── Support/
│   └── bootstrap.php
├── tools/
│   ├── deploy/                # bash deploy scripts
│   └── quality/               # PHP lint, etc.
├── translations/              # (целевое) Symfony translations
├── var/                       # cache, log (gitignored)
├── vendor/                    # composer dependencies (gitignored)
├── node_modules/              # npm dependencies (gitignored)
├── composer.json
├── package.json
├── docker-compose.yml
├── docker-compose.override.yml
├── Makefile
├── README.md
└── AGENTS.md
```

## Ключевые особенности

- **`public_html/` вместо `public/`.** Закреплено в `composer.json` через `extra.public-dir`. Это нужно для OSPanel и совместимости с production VPS, где web root всегда `current/public_html`. Не переименовывать.
- **`src/Module/<Name>` как граница bounded context.** Нет общего `src/Controller/`, `src/Form/`, `src/EventSubscriber/`. Контроллеры, формы и subscribers лежат внутри модуля, в `UI/` или `Infrastructure/Http`.
- **`tools/deploy/`** — единственное место для deploy bash; код Symfony про деплой не знает.
- **`config/services.yaml`** ограничивает auto-registration: исключены `src/Kernel.php`, `src/Module/*/Domain/Entity/`, `src/Module/*/Infrastructure/Doctrine/Entity/` (Doctrine entities никогда не должны попадать в DI как сервисы).

## `src/` — детально

### `src/Kernel.php`

Стандартный Symfony Kernel. Не модифицируется без архитектурной необходимости.

### `src/Module/<Name>/`

Модуль bounded context.

```text
src/Module/Content/
├── Domain/
│   ├── Entity/
│   │   ├── Page.php
│   │   └── PageBlock.php
│   ├── Enum/
│   │   ├── PageStatus.php
│   │   ├── PageType.php
│   │   └── BlockType.php
│   ├── Exception/
│   │   └── ContentNotFoundException.php
│   └── Repository/
│       ├── PageRepositoryInterface.php
│       └── PageBlockRepositoryInterface.php
├── Application/
│   ├── Command/
│   │   ├── CreatePageCommand.php
│   │   └── ...
│   ├── DTO/
│   │   ├── PageOutput.php
│   │   └── PageBlockOutput.php
│   ├── Handler/
│   │   ├── CreatePageHandler.php
│   │   └── ...
│   └── Service/
│       ├── PublicPageResolver.php
│       ├── PublicPageView.php
│       ├── PageBlockView.php
│       └── ContentId.php
├── Infrastructure/
│   └── Repository/
│       ├── DoctrinePageRepository.php
│       └── DoctrinePageBlockRepository.php
├── UI/
│   ├── Admin/
│   │   ├── PageApiController.php
│   │   ├── PageBlockApiController.php
│   │   ├── ContentApiResponder.php
│   │   └── JsonRequest.php
│   └── Web/
│       ├── PublicPageController.php
│       └── TwigBlockRenderer.php
└── README.md
```

| Папка | Что класть | Что НЕ класть |
|---|---|---|
| `Domain/Entity` | Doctrine entities с rich behaviour и инвариантами | `Symfony\HttpFoundation`, `Twig`, обращения к `EntityManager`, любой I/O |
| `Domain/ValueObject` | immutable PHP-объекты с валидацией | persistence-аннотации, side effects |
| `Domain/Enum` | backed PHP enums с label/значение | методы, делающие I/O |
| `Domain/Repository` | **только interface** | конкретные SQL-вызовы |
| `Domain/Service` | чистые domain services без I/O | контроллеры, шаблоны |
| `Domain/Event` | immutable доменные события | реакции на события (это `Application` или `Infrastructure`) |
| `Domain/Exception` | Domain-specific exceptions | HTTP exceptions |
| `Application/Command` | input DTO для use case | бизнес-правила |
| `Application/Query` | read DTO для use case | side effects |
| `Application/Handler` | use case (одна публичная точка входа `__invoke` или `handle`) | HTTP-знание, Request/Response |
| `Application/DTO` | output DTO (для UI/API) | Doctrine relations |
| `Application/Service` | application-level capability (resolver, registry) | mass-сервисы со 100 методов |
| `Infrastructure/Doctrine` | listeners, ORM mappings (не attributes), naming strategies | бизнес-правила |
| `Infrastructure/Repository` | реализации `*RepositoryInterface` | бизнес-правила |
| `Infrastructure/Http` | EventSubscriber, RequestStack-зависимый код модуля | бизнес-правила |
| `Infrastructure/Security` | voters, user checkers модуля | бизнес-правила |
| `Infrastructure/Integration` | (целевое) HTTP-клиенты | Domain |
| `UI/Admin` | admin HTML и admin API контроллеры | бизнес-логика |
| `UI/Web` | публичные контроллеры | бизнес-логика |
| `UI/Twig` | `AbstractExtension`, runtime helpers | бизнес-логика |

### `src/Shared/`

Shared kernel. Используется любым модулем.

```text
src/Shared/
├── Domain/
│   ├── Contract/
│   │   └── TimestampedEntityInterface.php
│   ├── Identifier/
│   │   └── EntityId.php
│   └── Trait/
│       ├── HasSoftDelete.php
│       └── HasTimestamps.php
├── Infrastructure/
│   ├── Doctrine/
│   │   └── TimestampListener.php
│   ├── Http/
│   │   ├── RequestIdSubscriber.php
│   │   └── SecurityHeadersSubscriber.php
│   ├── Logging/
│   │   ├── TelegramErrorHandler.php
│   │   ├── Message/
│   │   │   └── SendTelegramLogMessage.php
│   │   ├── MessageHandler/
│   │   │   └── SendTelegramLogMessageHandler.php
│   │   └── Processor/
│   │       ├── PiiRedactorProcessor.php
│   │       ├── ReleaseProcessor.php
│   │       ├── RequestProcessor.php
│   │       └── UserProcessor.php
│   └── Upload/
│       ├── UploadValidator.php
│       ├── UploadSecurityException.php
│       └── ValidatedUpload.php
├── UI/
│   ├── Http/
│   │   └── HealthCheckController.php
│   ├── Twig/
│   │   └── ViteAssetExtension.php
└── README.md
```

Правило: **Shared не зависит от конкретных модулей**. Если в `Shared` появляется ссылка на `App\Module\Content\...`, это ошибка — переносить в модуль.

## `config/`

| Файл | Назначение |
|---|---|
| `bundles.php` | Список Symfony bundles |
| `services.yaml` | DI с auto-wire/auto-configure, alias’ы repository interfaces, конфигурация subscriber’ов |
| `routes.yaml` | Auto-discovery контроллеров через attributes |
| `routes/security.yaml`, `routes/framework.yaml` | Дополнительные маршруты |
| `packages/security.yaml` | Firewalls, role hierarchy, password hashers |
| `packages/doctrine.yaml` | DBAL/ORM, naming strategy, prod cache pools |
| `packages/messenger.yaml` | Doctrine transport `async` + `failed` |
| `packages/cache.yaml` | Redis app/system cache + пулы `cache.public_page`, `cache.settings`, `cache.menu`, `cache.seo` |
| `packages/monolog.yaml` | Каналы (audit, admin, seo, lead, media, deploy, business, critical) и handlers |
| `packages/twig.yaml` | Twig глобалы и extensions |
| `packages/framework.yaml` | Базовые настройки framework bundle |
| `packages/router.yaml` | Базовые настройки router |
| `packages/validator.yaml`, `csrf.yaml`, `mailer.yaml`, `translation.yaml`, `property_info.yaml`, `routing.yaml` | Тематическая конфигурация |
| `packages/doctrine_migrations.yaml` | Путь миграций |

Запрещено: класть бизнес-логику в `config/services.yaml`.

## `migrations/`

Doctrine Migrations 4. Имена `VersionYYYYMMDDHHMMSS.php`, монотонно возрастают по дате. Подробно — [18-migrations](18-migrations.md).

## `templates/`

```text
templates/
├── base.html.twig
├── admin/
│   ├── dashboard.html.twig
│   └── security/login.html.twig
└── public/
    ├── home.html.twig
    ├── page/show.html.twig
    └── blocks/
        ├── default.html.twig
        ├── hero.html.twig
        ├── seo_text.html.twig
        └── text.html.twig
```

Подробно — [21-templates-and-twig](21-templates-and-twig.md).

## `tests/`

```text
tests/
├── Unit/                      # без Kernel
├── Integration/               # с Kernel + БД (миграции на тесте)
├── Functional/                # с HTTP client
├── Support/                   # хелперы (SchemaTestHelper)
└── bootstrap.php
```

Подробно — [31-testing-strategy](31-testing-strategy.md).

## `tools/`

```text
tools/
├── deploy/                    # bash + nginx/systemd templates
│   ├── common.sh
│   ├── deploy-staging.sh
│   ├── deploy-production.sh
│   ├── rollback.sh
│   ├── health-check.sh
│   ├── shared-env-example.sh
│   └── templates/
│       ├── nginx-staging.conf
│       ├── nginx-production.conf
│       ├── zaborprofil-messenger-staging.service
│       └── zaborprofil-messenger.service
└── quality/
    └── php-lint.php
```

## `assets/`

```text
assets/
├── admin/                     # React + TypeScript admin SPA
└── site/                      # Public site assets
```

Билдятся Vite в `public_html/build/`. Подробно — [22-frontend-assets](22-frontend-assets.md).

## `docker/`

```text
docker/
├── nginx/default.conf
├── node/Dockerfile
├── php/
│   ├── Dockerfile
│   ├── php.ini
│   └── xdebug.ini
└── postgres/init.sql
```

Только для local dev. На production не используется.

## `var/` и `vendor/` и `node_modules/`

Игнорируются Git. На VPS `var/log/` и `var/cache/` — внутри release; `var/share/` — целевое (shared между релизами для долгоживущих данных).

## `public_html/`

```text
public_html/
├── index.php                  # Symfony front controller
├── build/                     # Vite manifest + bundles
└── uploads/                   # User-uploaded files (через shared mount)
```

Запрещено хранить в `public_html/` любые файлы за пределами этого списка.

## Запрещённые расположения

| Что | Куда **нельзя** |
|---|---|
| Бизнес-логика | `Controller`, `Twig`, `EventSubscriber` (кроме инфраструктурных), `Migration`, `Repository` |
| Doctrine `EntityManager` | `Domain`, `Application/Handler` (за исключением unit-of-work через `flush()` в репозитории) |
| Symfony `Request` / `Response` | `Application`, `Domain` |
| `App\Module\X` импорты | `App\Shared\*` |
| Конкретные SQL | `Domain/Repository` (там interface) |
| Бизнес-правила | `Migration` |

См. [04-layer-rules](04-layer-rules.md) для расширенного списка.
