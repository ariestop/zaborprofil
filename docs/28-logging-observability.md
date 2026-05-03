# 28. Logging и observability

См. также [LOGGING.md](legacy/LOGGING.md).

## Стек

- Monolog 3 через `symfony/monolog-bundle`.
- Каналы и handlers в `config/packages/monolog.yaml`.
- Critical alerts через Telegram (через Messenger).
- Целевое: structured JSON logs + ship в централизованную систему (Loki / OpenSearch).

## Каналы

| Канал | Что логирует |
|---|---|
| `app` (default) | общее приложение |
| `security` | login/logout, access denied, throttling |
| `admin` | действия в admin API |
| `audit` | целевое — критичные действия (publish, role change, user create) |
| `seo` | sitemap generation, redirect resolution |
| `lead` | отправка форм, антиспам |
| `media` | uploads, delete, processing |
| `deploy` | события деплоя (целевое — `release.deployed`) |
| `business` | бизнес-события (lead.received, order.placed) |
| `critical` | всё с уровнем `error+`, отправляется в Telegram |
| `console` | CLI |

## Handlers

В `dev`/`test`:

- `main` — `var/log/app.log`, level `debug`.
- Канальные — отдельные файлы (`security.log`, `admin.log`, etc.).
- `console` — на STDOUT.

В `prod`:

- `main` — `fingers_crossed`, action_level `error`, buffer 50, json formatter.
- `nested` (для critical) — `var/log/critical.log`, debug, json.
- Канальные — те же файлы (rotated через logrotate).
- `critical` — `TelegramErrorHandler` (через async messenger).

## Processors

Все включены глобально через `services.yaml`:

| Processor | Что добавляет |
|---|---|
| `RequestProcessor` | `request_id`, `method`, `path`, `ip` |
| `UserProcessor` | `user.id`, `user.email` (если залогинен) |
| `ReleaseProcessor` | `release.tag`, `release.env`, читает `var/release-info.json` |
| `PiiRedactorProcessor` | удаляет/маскирует чувствительные данные (passwords, tokens, emails — целевое) |

## Request ID

`Shared\Infrastructure\Http\RequestIdSubscriber`:

- На `kernel.request` принимает `X-Request-Id` от клиента или генерирует ULID.
- Кладёт в `Request` attribute, в логи через `RequestProcessor`, и в response header.
- Messenger stamp с request_id переносит контекст в worker.

## Что обязательно логируется

- Все ошибки 5xx (level `error+`).
- Login attempts (success / fail).
- Logout.
- Admin actions (create/update/publish/archive/delete).
- Failed CSRF / Origin checks.
- Rate limit hits.
- Uploads (success / fail).
- Deploy события.
- Slow queries (через Doctrine middleware — целевое).
- Async message handle (start/end/fail).
- Cache failures (Redis недоступен).

## Что логировать НЕЛЬЗЯ

- Plain пароли.
- Токены (CSRF, JWT, API keys).
- Полный body запроса с PII (только размер / hash).
- `DATABASE_URL` с паролем.
- Telegram токен.
- Card data, если когда-нибудь появятся.

`PiiRedactorProcessor` должен покрывать эти кейсы. Но в коде — никогда не писать `logger->info('user logged in', ['password' => $password])`.

## Уровни

| Уровень | Когда |
|---|---|
| `debug` | пошаговое поведение, только dev |
| `info` | обычные события (login.success, page.published) |
| `notice` | необычное, но не ошибка (rate limit warning) |
| `warning` | потенциальная проблема (cache miss, slow query) |
| `error` | сбой, требует внимания, но request обработан |
| `critical` | сбой, требующий немедленного вмешательства (DB unreachable) |
| `alert` | system-wide outage |
| `emergency` | catastrophe |

`critical+` — идёт в Telegram.

## Примеры хороших логов

```php
$this->logger->info('content.page.published', [
    'page_id' => (string) $page->id(),
    'path' => $page->path(),
    'actor_id' => $userId,
]);

$this->mediaLogger->error('media.upload.failed', [
    'reason' => $e->getMessage(),
    'size' => $size,
    'mime' => $mime,
]);
```

Правила:

- Action ID: `<channel>.<resource>.<verb>` (`content.page.published`, `media.upload.failed`).
- Контекст — структура с явными ключами, не «всё в `message`».
- Никаких многострочных stack trace в `message` — это в `exception`.

## Log event categories (canonical)

- `http.request`
- `http.error`
- `admin.action`
- `security.auth.success` / `security.auth.fail`
- `security.access_denied`
- `domain.event.<name>` (целевое)
- `application.use_case.<name>`
- `doctrine.query.slow`
- `messenger.message.handle.start` / `.ok` / `.fail`
- `cache.error`
- `deploy.release.start` / `.ok` / `.fail`
- `file.upload.ok` / `.fail`
- `seo.sitemap.generate` / `seo.redirect.applied`

## Telegram critical handler

`Shared\Infrastructure\Logging\TelegramErrorHandler`:

- Срабатывает на `error+` в каналах кроме `event`/`doctrine`/`console`.
- Складывает в очередь `SendTelegramLogMessage`.
- Worker отправляет в Telegram bot API.
- На стороне Telegram — отдельная группа alerts.
- Если Telegram недоступен — сообщение остаётся в Doctrine queue до retry.

## Logrotate

На VPS — `/etc/logrotate.d/zaborprofil`:

```text
/var/www/zaborprofil/current/var/log/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    copytruncate
}
```

## Observability roadmap

- Prometheus exporter (PHP-FPM, Nginx, Postgres, Redis).
- Loki/Grafana для logs.
- OpenTelemetry tracing (целевое).
- Sentry для error tracking (опционально).
- Health endpoint `app:status` с подробной диагностикой.

## Anti-patterns

- `error_log()` напрямую (минует Monolog).
- `dump($var)` в production коде (включает Symfony Debug, в `prod` — no-op, но всё равно бесполезно).
- Logging в Twig (`{{ dump(...) }}`) — никогда.
- Logging пароля.
- Огромные сериализованные структуры в каждом log.
- Уровень `debug` на production — диск переполняется.

## Чек-лист новой фичи и логирования

- [ ] Использован правильный канал.
- [ ] Используется action ID `<channel>.<resource>.<verb>`.
- [ ] Контекст структурированный.
- [ ] Нет PII / секретов.
- [ ] Errors через `error+`.
- [ ] Test проверяет логирование (если критично).

## Связанные документы

- [29-healthchecks](29-healthchecks.md)
- [30-error-handling](30-error-handling.md)
- [37-runbooks](37-runbooks.md)
- [LOGGING.md](legacy/LOGGING.md)
