# 02. Высокоуровневая архитектура

## Стиль архитектуры

Проект построен как **Modular Monolith с Clean Architecture внутри каждого модуля**.

- Один Symfony-приложение, один deployment unit.
- Внутри `src/Module/<Name>` — четыре слоя: `Domain`, `Application`, `Infrastructure`, `UI`.
- `src/Shared` — утилиты, контракты, инфраструктурные адаптеры, общий UI.
- Жёсткое направление зависимостей: `UI -> Application -> Domain <- Infrastructure`.

## High-level diagram

```mermaid
flowchart LR
    Browser[Browser / Bot] -->|HTTPS| Nginx
    Nginx -->|FastCGI| PhpFpm[PHP-FPM]
    PhpFpm --> Kernel[Symfony Kernel]

    subgraph App[Symfony Application]
        Kernel --> Router
        Router --> Controller[UI Controller]
        Controller --> Application
        Application --> Domain
        Application --> RepoIface[Repository Interface]
        RepoIface -.implements.- Doctrine
        Application --> CacheIface[Cache / Redis adapter]
        Application --> Mailer[Mailer]
        Application --> Bus[Messenger bus]
    end

    Doctrine --> Postgres[(PostgreSQL 18)]
    CacheIface --> Redis[(Redis 8)]
    Bus --> DoctrineQueue[(messenger_messages)]
    Mailer --> SMTP[(SMTP)]
    Worker[Messenger Worker] --> Bus

    subgraph FrontPipeline[Frontend pipeline]
        Vite[Vite + Vue 3 + Tailwind] --> Manifest[manifest.json]
    end
    Controller --> Twig
    Twig --> Manifest
```

## Lifecycle: HTTP request

См. подробно в [07-request-flow](07-request-flow.md).

```mermaid
sequenceDiagram
    participant U as Browser
    participant N as Nginx
    participant F as PHP-FPM
    participant K as Symfony Kernel
    participant R as Router
    participant C as Controller
    participant A as Application Handler
    participant D as Domain
    participant I as Infrastructure (Doctrine/Redis/...)
    participant T as Twig

    U->>N: HTTP request
    N->>F: FastCGI
    F->>K: handle(Request)
    K->>K: kernel.request listeners (RequestId, Security, CSRF, AdminOrigin, Redirects)
    K->>R: match(path)
    R->>C: dispatch
    C->>C: parse DTO / validate
    C->>A: invoke use case
    A->>D: domain operations
    A->>I: persist / cache / mail / dispatch
    A-->>C: result DTO / Page view
    C->>T: render template (или JSON)
    T-->>K: Response
    K->>K: kernel.response listeners (SecurityHeaders, AdminNoIndex)
    K-->>F: Response
    F-->>N: Response
    N-->>U: HTTP response
```

## Lifecycle: console command

```mermaid
sequenceDiagram
    participant Op as Operator/Cron
    participant CLI as bin/console
    participant K as Console Kernel
    participant Cmd as Command class
    participant App as Application Service
    participant Inf as Infrastructure

    Op->>CLI: php bin/console app:something
    CLI->>K: bootstrap
    K->>Cmd: execute()
    Cmd->>App: вызов use case
    App->>Inf: persist/cache/...
    App-->>Cmd: результат
    Cmd-->>CLI: exit code
```

## Lifecycle: Messenger worker

См. подробно в [24-messenger-and-queues](24-messenger-and-queues.md).

```mermaid
sequenceDiagram
    participant W as messenger:consume
    participant T as Doctrine transport (messenger_messages)
    participant H as Message Handler
    participant App as Application/Domain
    participant Ext as External (SMTP / Telegram / ...)

    loop каждое сообщение
        W->>T: pop()
        T-->>W: message
        W->>H: handle(message)
        H->>App: операция
        App->>Ext: side effect
        Ext-->>App: ack/fail
        App-->>H: ok / throw
        H-->>W: ack
        W->>T: delete OR move to failed
    end
```

## Lifecycle: frontend assets

```mermaid
flowchart LR
    src[assets/site + assets/admin] --> Vite[vite build]
    Vite --> Build[public_html/build/]
    Build --> Manifest[manifest.json]
    Twig[ViteAssetExtension] --> Manifest
    Browser --> Build
```

## Слои

| Слой | Где живёт | Роль | Можно ли зависеть от Symfony/Doctrine |
|---|---|---|---|
| Presentation (Twig templates) | `templates/` | SSR разметка | Только Twig API |
| Controller / UI | `src/Module/*/UI`, `src/Shared/UI` | Вход HTTP/CLI, парсинг DTO | Да, Symfony HTTP Foundation |
| Application | `src/Module/*/Application` | Use cases, command/query, DTO, оркестрация | Только PHP/Symfony Validator/PSR; **не** Request/Response/EntityManager |
| Domain | `src/Module/*/Domain`, `src/Shared/Domain` | Сущности, value objects, инварианты, repository interfaces | Только PHP. Допустимы Doctrine attributes на Entity, но без вызова EM |
| Infrastructure | `src/Module/*/Infrastructure`, `src/Shared/Infrastructure` | Реализации интерфейсов: Doctrine, Redis, Mailer, FileStorage | Да |
| Persistence | `src/**/Infrastructure/Doctrine` | Doctrine repos / listeners | Да |
| Integration | `src/**/Infrastructure/Integration` (целевое) | HTTP-клиенты, внешние API | Да |
| Deployment | `tools/deploy/`, `docker/`, `.github/workflows/` | Поставка приложения | n/a |

Подробные правила — [04-layer-rules](04-layer-rules.md).

## Container diagram

```mermaid
C4Context
title Container diagram (фактическое состояние)

Person(visitor, "Visitor")
Person(admin, "Admin / Editor")

System_Boundary(s, "zaborprofil") {
    Container(nginx, "Nginx", "1.30+", "Reverse proxy, статика, TLS")
    Container(php, "PHP-FPM", "8.5", "Symfony 8 application")
    Container(worker, "Messenger Worker", "PHP CLI", "Async jobs")
    ContainerDb(pg, "PostgreSQL", "18", "Контент, sessions opt., messenger_messages")
    ContainerDb(redis, "Redis", "8", "Cache pools")
    Container(node, "Vite build", "Node 25.9", "Pre-build assets, dev only")
    Container(smtp, "SMTP / Mailpit", "", "Письма")
    Container(tg, "Telegram bot", "", "Critical alerts")
}

Rel(visitor, nginx, "HTTPS")
Rel(admin, nginx, "HTTPS / Vue SPA")
Rel(nginx, php, "FastCGI")
Rel(php, pg, "PDO pgsql")
Rel(php, redis, "Predis")
Rel(php, smtp, "Mailer")
Rel(php, tg, "TelegramErrorHandler")
Rel(worker, pg, "Pulls messenger_messages")
Rel(worker, smtp, "")
Rel(worker, tg, "")
```

## Направление зависимостей

```mermaid
flowchart LR
    UI --> Application
    Application --> Domain
    Infrastructure -.implements.-> Domain
    Infrastructure -.implements.-> Application
    UI -.uses.-> Domain
```

- Стрелка `-->` — `requires`.
- Стрелка `-.implements.->` — реализует контракт.
- Domain **никогда** не импортирует Symfony, Doctrine, Twig, Predis, FS.

## Разделение зон Front / Admin / API / Dev

```mermaid
flowchart LR
    Public[/Public site/] --> FrontControllers[UI/Web controllers]
    AdminUI[/admin UI shell/] --> AdminControllers[UI/Admin/* HTML]
    AdminAPI[/admin/api/*/] --> AdminApiControllers[UI/Admin/* JSON]
    DevTools[/_(profiler|wdt)/] --> Symfony[dev-only bundles]

    FrontControllers -.no admin auth.- Application
    AdminControllers --> Firewall[Symfony firewall main]
    AdminApiControllers --> Firewall
    Firewall --> AdminPermissionVoter
    DevTools --> DevOnly[only APP_ENV=dev/test]
```

Правила:

- `^/admin/api` — JSON, требует CSRF + same-origin Origin/Referer.
- `^/admin` — HTML admin, requires `ROLE_ADMIN`.
- Front catch-all (`PublicPageController`) — приоритет `-100`, чтобы не перехватывать admin/API.
- Dev-route не должно быть на production окружении (`when@dev` в bundles.php).

См. [12-admin-area](12-admin-area.md), [13-front-area](13-front-area.md), [14-api-area](14-api-area.md), [15-dev-area](15-dev-area.md).

## Deployment diagram (target)

```mermaid
flowchart LR
    GH[GitHub] -->|tag v*| Actions[Actions Deploy]
    Actions -->|SSH| StagingVPS[Staging VPS]
    Actions -->|SSH after staging| ProdVPS[Production VPS]

    subgraph ProdVPS[Production VPS]
        direction TB
        Releases[releases/<ts>]
        Shared[shared/.env.local + uploads + var/log]
        Current[current -> releases/<ts>]
        Nginx2[nginx]
        Php2[php-fpm 8.5]
        Worker2[systemd messenger@*]
        Pg2[(PostgreSQL 18)]
        Redis2[(Redis 8)]
    end

    Nginx2 --> Php2
    Php2 --> Pg2
    Php2 --> Redis2
    Worker2 --> Pg2
```

## Где живёт бизнес-логика

| Должна жить | Не должна жить |
|---|---|
| `Domain/Entity` (rich behaviour, инварианты) | `Controller` — никогда |
| `Domain/Service` (вычисления без I/O) | `Twig` — никогда |
| `Application/Handler` (оркестрация транзакций, событий, side effects) | `EventSubscriber` (кроме инфраструктурных subscriber’ов: security headers, request_id, admin csrf) |
| `Domain/ValueObject` (валидируемые value typing) | `Repository` — это адаптер, не бизнес-логика |
|  | `Migration` — миграция меняет только схему/данные, не вызывает Domain |

## Почему контроллер тонкий

- Контроллер — это адаптер транспорта (HTTP, CLI). Он знает Request/Response, но **не** знает бизнес-инвариантов.
- Тонкий контроллер легко тестируется functional-тестом, его легко переключить с HTML на API без переписывания логики.
- Толстый контроллер блокирует переиспользование use case (например, тот же CreatePage из CLI или из messenger).

## Почему Entity не может быть god object

- God-entity нарушает SRP: смешивает persistence, бизнес-правила, отрисовку, валидацию, integration calls.
- Симптом god-entity: 30+ методов, getter/setter на всё, 5+ зависимостей в конструкторе, ссылки на Symfony/Twig.
- В проекте Entity = маленькое, валидирует своих инварианты в конструкторе/методах. См. [Page](../src/Module/Content/Domain/Entity/Page.php).

## Почему Service layer должен быть осмысленным

Запрещено делать `App\Service\PageService` со 100 разнотипных методов. Вместо этого — отдельные `Application\Handler\<Action>Handler` или `Application\Service\<Capability>` (например, `PublicPageResolver`, `SettingsRegistry`). Имя сервиса должно отражать **способность**, а не «тут всё про Page».

## Связанные документы

- [03-project-structure](03-project-structure.md)
- [04-layer-rules](04-layer-rules.md)
- [06-module-architecture](06-module-architecture.md)
- [07-request-flow](07-request-flow.md)
