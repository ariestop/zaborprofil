# Lead

Модуль отвечает за публичные заявки, pipeline обработки, антиспам и уведомления.

- Public API: `POST /api/leads`.
- Public UI: SSR partial `public/partials/lead_form.html.twig`, отправка через `assets/site/app.ts`.
- Pipeline statuses: `new`, `in_progress`, `done`, `spam`.
- Anti-spam: honeypot `website`, минимальное время заполнения, счётчик по IP, link scoring. Это мягкие признаки: заявка сохраняется со статусом `spam` (ответ `202`).
- Жёсткий лимит: не больше 10 запросов в час с одного IP (`limiter.public_leads`, sliding window). Считается каждый запрос, в том числе битый и спам. Сверх лимита — `429 {error, code: TOO_MANY_REQUESTS}` с `Retry-After`, ничего не сохраняется и не уведомляется. IP — `Request::getClientIp()`, см. [27-config-and-env](../../../docs/27-config-and-env.md#trusted-proxies--hosts).
- Consent snapshot хранит текст согласия, policy URL, page URL, IP, User-Agent и timestamp.
- Admin CRM API (`/admin/api/leads`): серверная пагинация, поиск (имя, телефон по цифрам, email, текст), фильтры по статусу/источнику/датам/ответственному, сортировка, счётчики по статусам.
- Карточка заявки: история в таблице `lead_events` (`status_changed`, `note`, `assigned`) + запись в audit log; `LeadWorkflow` — единая точка смены статуса, назначения и добавления заметок.
- Права: `leads.view`, `leads.manage`, `leads.export` (только админы); открытие карточки и экспорт пишутся в audit log без ПДн.
- Экспорт CSV: `LeadCsvExporter` экранирует ячейки, начинающиеся с `= + - @`, табуляции и `\r` (защита от CSV-инъекций), добавляет UTF-8 BOM.
- Notifications: email через `LEAD_NOTIFICATION_EMAIL`; Telegram через `LEAD_TELEGRAM_BOT_TOKEN` + `LEAD_TELEGRAM_CHAT_ID`.
