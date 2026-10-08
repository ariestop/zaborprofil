# 28. Logging и observability

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
| `observability` | алерты, которые должны дойти до владельца: `lead.notification.failed`, `admin.client.error`; уровень `error+` уходит в Telegram и в Sentry |
| `critical` | всё с уровнем `error+`, отправляется в Telegram |
| `console` | CLI |

Фактические события в канале `business`:

- `page.published`
- `page.pathChanged`
- `setting.updated`
- `setting.deleted`
- `lead.created`

Фактические события в канале `observability`:

| Событие (message) | Уровень | Когда |
|---|---|---|
| `lead.notification.failed: заявка <id> не доставлена ни по одному каналу` | `error` | email и Telegram уведомление о новой заявке не доставлены (или единственный настроенный канал упал). Уходит в Telegram и Sentry |
| `lead.notification.partial` | `warning` | один канал доставил, другой упал |
| `lead.notification.not_configured` | `warning` | не задан ни `LEAD_NOTIFICATION_EMAIL`, ни пара `LEAD_TELEGRAM_*` |
| `admin.client.error: <текст ошибки>` | `error` | необработанная ошибка в SPA админки (`ErrorBoundary`, `window.onerror`, `unhandledrejection`) |

В контексте — только идентификаторы и коды причин (`reasons`: класс исключения, а не его текст), без телефона, email и токенов.
- `lead.created`, `lead.status_changed`, `lead.assigned` — только идентификаторы и коды статусов; телефон попадает в лог исключительно в маскированном виде (`7*********33`: первая и две последние цифры), тексты заметок и сообщений не логируются

## Error tracking (Sentry)

- Пакет `sentry/sentry-symfony`, конфигурация `config/packages/sentry.yaml`, включается переменной `SENTRY_DSN` (пусто — выключено, в dev/test так и остаётся). DSN можно направить на self-hosted Sentry/GlitchTip.
- Отправляются необработанные исключения (kernel/console/messenger) и записи канала `observability` уровня `error+`.
- Персональные данные не уходят: `send_default_pii=false`, тело запроса не отправляется (`max_request_body_size: none`), а `SentryEventScrubber` дополнительно убирает cookies, заголовки, query-строку, IP и email пользователя, breadcrumbs, имя сервера и маскирует email/телефоны/IP в тексте (`PiiMasker`). Остаётся маршрут, метод и id пользователя.
- Tracing выключен (`tracing.enabled: false`), чтобы не оборачивать кэш, БД и Twig.
- Для РФ-хостинга предпочтителен self-hosted инстанс; облачный Sentry использовать только после согласования.

## Клиентские ошибки админки

- `POST /admin/api/client-errors` (`ClientErrorApiController`): принимает `message`, `source` (`error-boundary` / `window-error` / `unhandled-rejection`), `url`, `stack`, `componentStack`, обрезает поля, оставляет только путь URL (без query/hash) и пишет `error` в канал `observability`.
- Защита: сессия админа, CSRF, Origin, лимит 20 отчётов за 10 минут на пользователя (`limiter.admin_client_errors`).
- Клиент: `admin/shared/lib/client-error-reporter.ts` — не отправляет одинаковые ошибки повторно, не более 5 за загрузку страницы, игнорирует `ApiError`, отмену запросов и шум `ResizeObserver`.

## Метрики на сводке админки

`GET /admin/api/system/observability` (право `system.view`) отдаёт данные для карточек на «Сводке»: ответы 5xx за час и за 24 часа, длина очереди Messenger (`pending` / `failed`), свободное место на диске. Новые заявки берутся из сводки заявок (`/admin/api/leads/summary`).

- Счётчик 5xx — `ServerErrorCounter`: почасовые корзины в `var/log/observability/http-5xx.json` (файловая система, без БД, общий каталог логов — не сбрасывается деплоем). Считаются главные запросы с ответом 5xx, кроме `/health*`, чтобы мониторинг не накручивал сам себя.
- Сводка подсвечивает в «Требует внимания» сообщения в `failed` и 5xx за последний час.

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
- Audit trail для критичных admin-изменений (`Page`, `PageBlock`, `Setting`, `Redirect`) с `old/new` значениями.
- Failed CSRF / Origin checks.
- Rate limit hits.
- Uploads (success / fail).
- Deploy события.
- Slow queries (через Doctrine middleware — целевое).
- Async message handle (start/end/fail).
- Cache failures (файловый кэш недоступен: каталог `var/cache/<env>/pools/app` не существует или нет прав на запись).

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
- Worker (`messenger:consume async`) отправляет в Telegram bot API через `TelegramMessageSender`.
- Токен и чат — `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` из контейнера (`.env.local` или `secrets:set`). Через `getenv()` их не читать: значения из `.env.local` в окружение процесса не попадают.
- На стороне Telegram — отдельная группа alerts.
- Если Telegram недоступен, отправка не повторяется: алерт теряется, повтор с тем же fingerprint не раньше чем через 5 минут.
- Без запущенного worker алерты копятся в таблице `messenger_messages` и не отправляются.

## Logrotate

Шаблон хранится в `tools/deploy/templates/zaborprofil-logrotate.conf`.
На VPS он устанавливается как `/etc/logrotate.d/zaborprofil`:

```text
/var/www/zaborprofil/shared/var/log/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    copytruncate
    create 0640 www-data www-data
}
```

Monitoring timer использует `tools/deploy/monitoring-check.sh` и может отправлять
алерты через `ALERT_WEBHOOK_URL` или Telegram-пару `ALERT_TELEGRAM_BOT_TOKEN` /
`ALERT_TELEGRAM_CHAT_ID`.

## Uptime-проверка `/health`

Внешняя проверка работает независимо от сервера: workflow `.github/workflows/uptime.yml` каждые 15 минут запускает `tools/monitoring/uptime-check.sh`.

- URL берутся из repository variable `UPTIME_URLS` (через пробел), например `https://zaborprofil.ru/health/ready`. Пусто — проверка пропускается.
- Ожидается HTTP 200 и `"status":"ok"` в теле; три попытки с паузой. При сбое job падает (GitHub присылает письмо) и, если заданы секреты `ALERT_TELEGRAM_BOT_TOKEN` / `ALERT_TELEGRAM_CHAT_ID`, отправляет сообщение в Telegram.
- Для закрытого Basic Auth стенда нужен секрет `UPTIME_BASIC_AUTH` (`логин:пароль`); он применяется ко всем URL списка, поэтому staging и production лучше проверять отдельными запусками/переменными.
- GitHub отключает scheduled workflow в репозитории без активности 60 дней; для production основной контроль остаётся за `monitoring-check.sh` на сервере.
- Скрипт покрыт тестом `tests/shell/uptime-check.sh` (запускается в CI).

## Observability roadmap

- Prometheus exporter (PHP-FPM, Nginx, MySQL).
- Loki/Grafana для logs.
- OpenTelemetry tracing (целевое).
- ~~Sentry для error tracking~~ — подключён (см. раздел «Error tracking»).
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
- [12-admin-area](12-admin-area.md)
