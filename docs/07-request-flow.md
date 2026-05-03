# 07. Request flow

Подробное описание пути запроса в системе.

## HTTP request — happy path

```mermaid
sequenceDiagram
    participant U as Client
    participant N as Nginx
    participant F as PHP-FPM
    participant K as Kernel
    participant Subs as kernel.request listeners
    participant R as Router
    participant C as Controller
    participant DTO as DTO/Validator
    participant H as Application Handler
    participant Repo as RepositoryInterface
    participant DC as DoctrineRepository
    participant DB as PostgreSQL
    participant Cache as Redis Cache
    participant T as Twig

    U->>N: GET /some-page
    N->>F: FastCGI
    F->>K: handle()
    K->>Subs: dispatch kernel.request
    Subs->>Subs: RequestIdSubscriber sets X-Request-Id
    Subs->>Subs: Firewall (security)
    Subs->>Subs: AdminApiCsrfSubscriber (если ^/admin/api)
    Subs->>Subs: AdminApiOriginSubscriber (если ^/admin/api)
    Subs->>Subs: RedirectKernelSubscriber (Seo)
    K->>R: match()
    R->>C: dispatch
    C->>DTO: parse + validate
    DTO-->>C: ok
    C->>H: __invoke(command)
    H->>Repo: getById / save
    Repo->>DC: query
    DC->>DB: SQL
    DB-->>DC: rows
    DC-->>Repo: Entity
    H-->>C: PageOutput
    C->>T: render template
    T-->>K: Response
    K->>Subs: kernel.response
    Subs->>Subs: SecurityHeadersSubscriber
    Subs->>Subs: AdminNoIndexSubscriber (если /admin*)
    K-->>F: Response
    F-->>N: Response
    N-->>U: 200 OK
```

## Validation failure

```mermaid
sequenceDiagram
    participant U
    participant C as Controller
    participant V as Validator

    U->>C: POST /admin/api/content/pages (invalid payload)
    C->>V: validate(dto)
    V-->>C: ConstraintViolationList
    C-->>U: 400 Bad Request (JSON errors via ContentApiResponder)
```

`Application` не должен запускаться при невалидном вводе. Контракт DTO — валидация **до** вызова handler.

## Domain error

```mermaid
sequenceDiagram
    participant C as Controller
    participant H as Handler
    participant E as Entity

    C->>H: invoke
    H->>E: publish()
    E--xH: throw InvalidArgumentException
    H--xC: пробрасывает или маппит в DomainException
    C->>C: маппит в HTTP 409/422
    C-->>U: 422 Unprocessable Entity
```

Domain exceptions не утекают как 500. Они конвертируются в осмысленный HTTP-код в контроллере или централизованном responder’е.

## Infrastructure error

Доступы к Redis/SMTP/Telegram не должны валить запрос пользователя без оснований. Стратегия по умолчанию:

- Cache недоступен — fallback на прямое чтение из БД, лог `cache.error`.
- SMTP недоступен — отправка через Messenger, retry с backoff.
- Telegram недоступен — лог критики не теряется (попадает в основной handler).
- БД недоступна — 503 + healthcheck показывает `not ready`.

## Not found

`PublicPageController` бросает `createNotFoundException()` при отсутствии страницы или статусе `Draft/Archived`. Symfony возвращает 404, рендерит `templates/bundles/TwigBundle/Exception/error404.html.twig` (целевое).

## Access denied

```mermaid
sequenceDiagram
    participant U
    participant FW as Symfony Firewall
    participant V as AdminPermissionVoter

    U->>FW: GET /admin/...
    FW->>V: vote(attribute, subject, token)
    V-->>FW: ACCESS_DENIED
    FW-->>U: 403 Forbidden (или redirect на login если не залогинен)
```

## Form submission (admin)

Большинство admin-операций идёт через JSON API; FormType используется только для login-формы и других чисто HTML-форм.

```mermaid
sequenceDiagram
    participant U
    participant C as Admin API Controller
    participant J as JsonRequest
    participant V as Validator
    participant H as Handler
    participant R as ContentApiResponder

    U->>C: POST JSON + X-CSRF-Token
    C->>J: parse JSON to DTO
    J->>V: validate
    V-->>C: ok / errors
    alt invalid
        C->>R: error(400, errors)
    else valid
        C->>H: invoke
        H-->>C: result
        C->>R: success(result)
    end
    R-->>U: JSON
```

## API request

Идентичен admin API, но:

- роуты под `/api/...` (целевое);
- авторизация через токен/JWT (целевое);
- без CSRF, но с rate-limiting и Origin checks для CORS.

## Console command

```mermaid
sequenceDiagram
    participant Op as Operator/Cron
    participant CLI as bin/console
    participant Cmd as Command
    participant H as Application Handler
    participant Inf as Infrastructure

    Op->>CLI: app:something --opt=value
    CLI->>Cmd: configure + execute
    Cmd->>H: invoke
    H->>Inf: persist/cache/...
    H-->>Cmd: result
    Cmd-->>CLI: 0 / 1
```

Console command — тонкий контроллер для CLI: парсит ввод, вызывает handler, печатает результат.

## Async message (Messenger)

См. [24-messenger-and-queues](24-messenger-and-queues.md).

```mermaid
sequenceDiagram
    participant H as Producer
    participant Bus as Messenger Bus
    participant T as Doctrine transport
    participant W as Worker
    participant MH as MessageHandler

    H->>Bus: dispatch(message)
    Bus->>T: insert into messenger_messages
    Note over W: messenger:consume async
    W->>T: pop
    T-->>W: message
    W->>MH: handle(message)
    MH-->>W: ok / throw
    alt ok
        W->>T: delete
    else throw retryable
        W->>T: backoff, retry
    else throw permanent
        W->>T: move to failed
    end
```

## Lifecycle событий Symfony, которые мы используем

| Событие | Где | Что делает |
|---|---|---|
| `kernel.request` | `RequestIdSubscriber` | Назначает `X-Request-Id` |
| `kernel.request` | Symfony Security Firewall | Auth |
| `kernel.request` | `AdminApiCsrfSubscriber` | CSRF для `^/admin/api` |
| `kernel.request` | `AdminApiOriginSubscriber` | Same-origin Origin/Referer |
| `kernel.request` | `RedirectKernelSubscriber` | 301/302 для `Redirect` |
| `kernel.controller` | (не используется) | резерв для DTO mapping |
| `kernel.exception` | (целевое) | централизованный JSON error для API |
| `kernel.response` | `SecurityHeadersSubscriber` | заголовки `X-Frame-Options` и т.д. |
| `kernel.response` | `AdminNoIndexSubscriber` | `X-Robots-Tag: noindex` для `/admin*` |
| Doctrine `prePersist`/`preUpdate` | `TimestampListener` | created_at/updated_at |
| Doctrine `postUpdate` | `PagePathChangeListener` | реакция на смену `Page.path` |

## Связанные документы

- [02-architecture](02-architecture.md)
- [08-controller-architecture](08-controller-architecture.md)
- [16-routing](16-routing.md)
- [30-error-handling](30-error-handling.md)
