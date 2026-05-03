# Заявки

W10 добавляет публичный lead pipeline.

## Public API

`POST /api/leads` принимает JSON:

- `source`, `name`, `phone`, `email`, `message`;
- `consent=true` и `consentText`;
- `pageUrl`, `policyUrl`, `formLoadedAt`;
- honeypot поле `website`.

Обычная заявка сохраняется со статусом `new` и возвращает `201`. Spam-заявка сохраняется со статусом `spam`, получает `spamScore`/`spamReasons` и возвращает нейтральный `202`.

## Anti-spam

`LeadAntiSpamChecker` проверяет:

- заполненный honeypot;
- отправку быстрее минимального времени заполнения;
- больше 5 заявок с одного IP в час;
- слишком много ссылок в сообщении.

## Уведомления

`LeadNotifier` отправляет email, если задан `LEAD_NOTIFICATION_EMAIL`, и Telegram-сообщение, если заданы `LEAD_TELEGRAM_BOT_TOKEN` и `LEAD_TELEGRAM_CHAT_ID`.
