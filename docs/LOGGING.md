# Логирование

Проект использует Monolog 3 и Symfony Messenger для асинхронных уведомлений.

## Request ID

Каждый HTTP-запрос получает `X-Request-Id`.

- Если заголовок пришел от клиента и соответствует безопасному формату, он сохраняется.
- Если заголовка нет, генерируется ULID.
- Значение попадает в response header и в Monolog `extra.request_id`.

## Monolog processors

Подключены processors:

- `RequestProcessor` — route, method, path, ip, user agent, duration, memory.
- `UserProcessor` — текущий пользователь и роли.
- `ReleaseProcessor` — environment, release, commit из `var/release-info.json`.
- `PiiRedactorProcessor` — маскирует email, телефон, IP и чувствительные ключи.

## Файлы логов

- `var/log/app.log`
- `var/log/security.log`
- `var/log/admin.log`
- `var/log/audit.log`
- `var/log/seo.log`
- `var/log/lead.log`
- `var/log/media.log`
- `var/log/deploy.log`
- `var/log/business.log`
- `var/log/critical.log` в production для fingers-crossed ошибок.

## Telegram alert sink

Ошибки уровня `error` и выше отправляются в `TelegramErrorHandler`.

Handler:

- считает fingerprint ошибки;
- троттлит повторяющиеся ошибки на 5 минут;
- отправляет `SendTelegramLogMessage` в Messenger transport `async`.

Worker читает настройки:

- `notifications.telegram_bot_token`;
- `notifications.telegram_chat_id`;

или fallback env:

- `TELEGRAM_BOT_TOKEN`;
- `TELEGRAM_CHAT_ID`.

Если настройки не заданы, уведомление молча пропускается и не блокирует запрос.
