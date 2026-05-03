# Lead

Модуль отвечает за публичные заявки, pipeline обработки, антиспам и уведомления.

- Public API: `POST /api/leads`.
- Public UI: SSR partial `public/partials/lead_form.html.twig`, отправка через `assets/site/app.ts`.
- Pipeline statuses: `new`, `in_progress`, `done`, `spam`.
- Anti-spam: honeypot `website`, минимальное время заполнения, IP rate limit, link scoring.
- Consent snapshot хранит текст согласия, policy URL, page URL, IP, User-Agent и timestamp.
- Notifications: email через `LEAD_NOTIFICATION_EMAIL`; Telegram через `LEAD_TELEGRAM_BOT_TOKEN` + `LEAD_TELEGRAM_CHAT_ID`.
