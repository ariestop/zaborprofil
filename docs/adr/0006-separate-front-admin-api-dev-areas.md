# ADR-0006: Разделение зон Front / Admin / API / Dev

## Статус

Accepted, 2026.

## Контекст

В типичной CMS публичные страницы, админка, JSON API и dev-инструменты часто смешиваются в одном пространстве маршрутов и контроллеров. Это создаёт:

- риск утечки админских действий через публичные роуты;
- сложность настройки firewall (один firewall на всё или зоопарк);
- сложность безопасности (CSRF/Origin/no-index применяются неравномерно);
- сложность кеширования (нельзя кешировать весь сайт, если admin делит namespace).

## Решение

Жёсткое разделение зон по префиксам и правилам:

| Зона | Префикс | Auth | Формат |
|---|---|---|---|
| Front (public) | без префикса | public | HTML SSR |
| Admin HTML | `^/admin` (без `/api`) | `ROLE_ADMIN` через firewall `main` | HTML |
| Admin API | `^/admin/api` | `ROLE_ADMIN` + CSRF + Origin same-site | JSON |
| Public API | `^/api/v1` (целевое) | token / JWT | JSON |
| Dev | `^/_(profiler\|wdt)` | `APP_ENV=dev/test` | HTML |
| Webhooks (целевое) | `^/webhooks/<source>` | HMAC | JSON |

Каждая зона:

- имеет свой набор controller’ов в `UI/Web|Admin|Api|Console`;
- свои subscriber’ы (admin: CSRF/Origin/NoIndex);
- свои правила кеша (Front cacheable, остальные — нет).

## Причины

- **Безопасность.** Никаких сюрпризов: `/admin*` — закрытая зона по умолчанию.
- **Чистый кеш.** Front можно кешировать reverse proxy без риска получить admin-страницу.
- **Tooling separation.** Dev-роуты невозможно открыть на prod при правильном `APP_ENV`.
- **Тестирование.** Легко functional-тестировать каждую зону отдельно.

## Последствия

- Каждый новый контроллер должен быть осознанно отнесён к зоне.
- Public API (`/api/v1`) — отдельная firewall и rate limiting (целевое).
- `PublicPageController` имеет catch-all `/{path}` с `priority: -100` — это **обязательно**, иначе он перехватывает admin/api/sitemap/robots.

## Альтернативы

- **Один firewall на всё** — быстрее настроить, но безопасность хуже.
- **Hostname-based separation** — overkill для одного домена.
- **Subdomain admin (admin.zaborprofil.ru)** — рассматривать в будущем для дополнительной изоляции; сейчас не нужно.

## Когда пересмотреть

- Появится потребность в публичной авторизованной зоне (личный кабинет) — отдельная зона `^/account`.
- Будет введён subdomain admin.

## Связанные документы

- [12-admin-area](../12-admin-area.md)
- [13-front-area](../13-front-area.md)
- [14-api-area](../14-api-area.md)
- [15-dev-area](../15-dev-area.md)
- [16-routing](../16-routing.md)
- [20-security-and-access-control](../20-security-and-access-control.md)
