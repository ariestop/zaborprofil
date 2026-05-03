# 30. Error handling

## Иерархия исключений

```text
\Throwable
├── \Error                              # PHP runtime
└── \Exception
    ├── \LogicException
    ├── \RuntimeException
    │   └── \UnexpectedValueException
    ├── \DomainException
    │   ├── App\Module\<X>\Domain\Exception\<Specific>NotFoundException
    │   └── App\Module\<X>\Domain\Exception\<Specific>ValidationException
    └── App\Shared\Application\Exception\ApplicationException
        ├── App\Shared\Application\Exception\AccessDeniedException
        └── App\Shared\Application\Exception\IdempotencyConflictException
```

## Domain exceptions

Наследуются от `\DomainException` или собственных доменных классов.

Примеры:

- `App\Module\Content\Domain\Exception\ContentNotFoundException`
- (целевое) `App\Module\Content\Domain\Exception\PagePathDuplicateException`
- (целевое) `App\Module\Lead\Domain\Exception\LeadAntispamRejectedException`

Правила:

- Domain exception **не знает** HTTP-кодов.
- Имя — глагольное / описательное (`...NotFound`, `...Invalid`, `...Duplicate`).
- Сообщения — машинно-читаемые ключи, не свободный текст для UI.

## Application exceptions

Создавать, когда нужно отделить ошибку сценария от доменной (например, idempotency conflict, transaction conflict).

## Validation exceptions

`Symfony\Component\Validator\Exception\ValidationFailedException` (или `ConstraintViolationListInterface`) — поднимаются Validator и обрабатываются в контроллере → 400/422.

## Infrastructure exceptions

- `Doctrine\DBAL\Exception\*` — БД.
- `Predis\PredisException` / `Symfony\Component\Cache\Exception\*` — Redis.
- `Symfony\Component\Mailer\Exception\TransportException` — SMTP.

В Application/Domain эти типы видеть **нельзя** — только в Infrastructure, где они ловятся и:

- логируются в нужном канале;
- маппятся в более общую `InfrastructureException` или domain-specific exception;
- по необходимости — пробрасываются в Messenger retry.

## HTTP exceptions

`Symfony\Component\HttpKernel\Exception\*` — для контроллеров:

- `NotFoundHttpException` — 404
- `AccessDeniedHttpException` — 403
- `BadRequestHttpException` — 400
- `UnprocessableEntityHttpException` — 422
- `ConflictHttpException` — 409
- `TooManyRequestsHttpException` — 429

В контроллере:

```php
try {
    $this->createPage->__invoke($command);
} catch (PagePathDuplicateException $e) {
    throw new ConflictHttpException('Page path already exists.', $e);
} catch (ValidationFailedException $e) {
    throw new UnprocessableEntityHttpException(/* ... */, $e);
}
```

## API error format (целевое)

```json
{
  "error": {
    "code": "page.path.duplicate",
    "message": "Page path already exists.",
    "details": {},
    "request_id": "01HZ..."
  }
}
```

## User-facing vs internal errors

- Public/Front 5xx — отдаётся стандартный `error500.html.twig` без stack trace.
- Public 404 — `error404.html.twig` с полезным навигационным предложением.
- Admin API 5xx — `ContentApiResponder` отдаёт generic `Internal server error`, оригинал — в логе.

## Retryable vs non-retryable

| Тип | Retryable | Пример |
|---|---|---|
| Network timeout (HTTP, Redis) | да (с backoff) | `Symfony HttpClient timeout` |
| DB deadlock | да (1–2 ретрая) | `RetryableException` Doctrine |
| Доменная валидация | нет | `PagePathDuplicateException` |
| Validation input | нет | `ValidationFailedException` |
| Permission denied | нет | `AccessDeniedHttpException` |
| Idempotency conflict | нет | `IdempotencyConflictException` |

В Messenger handler — выбрасывать subclass `RecoverableMessageHandlingExceptionInterface` для retryable, `UnrecoverableMessageHandlingExceptionInterface` для permanent.

## Не теряем контекст

- Всегда оборачивать в новое исключение через `previous`: `throw new ConflictHttpException('...', $e)`.
- Логировать через `$logger->error('...', ['exception' => $e])` — Monolog развернёт stack trace.
- Не делать `throw new \Exception($e->getMessage())` — теряем тип и стек.

## Что НЕ возвращать пользователю

- Stack trace.
- Имена внутренних классов.
- Доступы к БД, ключи Redis.
- Содержимое `.env`.
- Текст SQL.
- Имена файлов с absolute path.

## Централизованный exception listener (целевое)

`App\Shared\UI\Http\ApiExceptionListener` на `kernel.exception` для `^/admin/api` и `^/api`:

- Маппит `\Throwable` → JSON-формат с `code`/`message`/`request_id`.
- Скрывает internals в `prod`, показывает в `dev`.
- Логирует с правильным каналом.

## Anti-patterns

- `catch (\Throwable $e) { return new Response('Error', 500); }`
- `catch (\Exception $e) { /* swallow */ }`
- Возврат разных JSON-форматов для разных endpoint’ов.
- Пробрасывание `Doctrine\Exception` в контроллер.
- Domain исключения с HTTP-кодами внутри.
- `die`/`exit` для аварийного выхода.
- `trigger_error(...)` — устаревшая практика.

## Чек-лист обработки ошибок

- [ ] Domain бросает domain-specific exception.
- [ ] Application пробрасывает или оборачивает в application exception.
- [ ] Infrastructure ловит низкоуровневые exceptions и маппит в осмысленные.
- [ ] Контроллер маппит в HTTP exception.
- [ ] Логирование с контекстом и `exception`.
- [ ] User видит безопасное сообщение.
- [ ] Test проверяет 4xx/5xx сценарий.

## Связанные документы

- [09-application-layer](09-application-layer.md)
- [10-domain-layer](10-domain-layer.md)
- [14-api-area](14-api-area.md)
- [28-logging-observability](28-logging-observability.md)
