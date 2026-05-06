# 20. Безопасность и контроль доступа

Разделяем на: **AuthN** (кто), **AuthZ** (что можно), **Transport security** (как защищены каналы), **Application hardening** (как защищён код).

См. также: [SECURITY.md](legacy/SECURITY.md), [ROLES.md](legacy/ROLES.md), [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md).

## Authentication

### Admin

`config/packages/security.yaml`:

- Provider: `admin_user_provider` → `AdminUser` (по `email`).
- Firewall `main` с `form_login` (`admin_login`).
- `user_checker: AdminUserChecker` — блокирует неактивных и при разлогинивает в активной сессии.
- `login_throttling`: 5 attempts / 15 минут на пару IP+identifier (отключён в `test`).

### Public API (целевое)

- Bearer token / JWT в `firewalls.api`.

## Authorization

### Role hierarchy

```yaml
ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
ROLE_ADMIN: [ROLE_EDITOR, ROLE_SEO, ROLE_MANAGER]
ROLE_EDITOR: []
ROLE_SEO: []
ROLE_MANAGER: []
```

### Access control

```yaml
- { path: ^/admin/login$, roles: PUBLIC_ACCESS }
- { path: ^/admin, roles: ROLE_ADMIN }
```

### Permissions

Использование `AdminPermissionVoter`:

```php
#[IsGranted(AdminPermission::PagesPublish->value)]
public function publish(...) {}
```

`AdminPermissionVoter` мапит permission → роль:

| Permission | Роли, у которых есть |
|---|---|
| `pages.view`, `pages.create`, `pages.edit`, `pages.publish`, `pages.delete` | `ROLE_EDITOR` (publish/delete — `ROLE_ADMIN`) |
| `seo.edit` | `ROLE_SEO`, `ROLE_ADMIN` |
| `media.upload`, `media.delete` | `ROLE_EDITOR`, `ROLE_ADMIN` |
| `leads.view`, `leads.manage` | `ROLE_MANAGER`, `ROLE_ADMIN` |
| `settings.edit`, `users.manage`, `system.manage` | `ROLE_ADMIN` / `ROLE_SUPER_ADMIN` |

При появлении сложных правил на конкретные сущности — выделить **resource voters** (например, `PageVoter::canEdit($page, $user)`).

## CSRF

- Login form — Symfony `enable_csrf: true`.
- Logout — `enable_csrf: true`.
- Admin JSON API — double-submit:
  - токен `admin_api` рендерится в `<meta name="admin-csrf-token">`;
  - React SPA шлёт `X-CSRF-Token`;
  - `AdminApiCsrfSubscriber` валидирует.

## Same-origin

`AdminApiOriginSubscriber` проверяет `Origin`/`Referer` против `SITE_URL`. Cross-origin admin запрос — `403`.

## Password hashing

```yaml
password_hashers:
    Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: auto
```

`auto` — Symfony выберет лучший доступный (argon2id если есть). В `test` — низкий cost для скорости.

## Session

- Cookies `Secure`, `HttpOnly`, `SameSite=Lax`.
- В production — только HTTPS, `Set-Cookie: Secure`.
- Storage — native PHP sessions (целевое можно перевести на Redis-backed).

## Remember me

Не используется. Целевое — добавить, если будет требование.

## Rate limiting

`symfony/rate-limiter` уже подключён.

Целевое (фактически только login throttling сейчас):

| Endpoint | Limit |
|---|---|
| `admin_login` | 5 / 15 min (login_throttling) |
| `lead.submit` | 5 req/min/IP |
| `api.public` (анонимный) | 60 req/min/IP |
| `api.public` (auth) | 600 req/min/token |

## File upload security

См. [25-files-and-uploads](25-files-and-uploads.md), [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md).

`UploadValidator`:

- размер,
- MIME через `finfo`,
- whitelist расширений,
- safe filename (без `..`, без spaces),
- путь сохранения вне `public_html` или с дополнительной защитой.

## Path traversal

- Никогда не строить путь как `$root . '/' . $userInput`. Использовать `realpath` + `str_starts_with($real, $root)`.
- В `Page::normalizePath()` запрещены `//`, символы вне `[a-z0-9_\-./]`.
- В uploads — нормализация имени файла.

## XSS

- Twig auto-escaping (по умолчанию `html`). Не отключать `|raw` без причины.
- HTML-входы из админки — sanitize (`HTMLPurifier` или `symfony/html-sanitizer` — целевое).
- JSON — `json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)`.

## SQL Injection

- Только параметризованные запросы (DQL/QueryBuilder/PDO bind).
- Никаких `'WHERE id = '.$id` в SQL.

## Secrets handling

См. [27-config-and-env](27-config-and-env.md).

- Секреты — в `.env.local` / `.env.production` (вне Git).
- `APP_SECRET` — уникальный per-environment.
- Symfony Secrets Vault (целевое) для prod вместо `.env.production`.

## Security headers

`SecurityHeadersSubscriber`:

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`
- `Permissions-Policy: ...`
- `Content-Security-Policy-Report-Only: ...` (целевое — full enforce)

## Nginx hardening

- HTTPS-only (`return 301 https` для `:80`).
- HSTS (после стабильного prod): `Strict-Transport-Security: max-age=...; includeSubDomains; preload`.
- TLS 1.2+, отключённый SSLv3/TLS 1.0/1.1.
- Не показывать `server_tokens`.

## Firewall и сеть

- PostgreSQL слушает только `localhost`.
- Redis слушает только `localhost`, с `requirepass`.
- SSH — только по ключам, `PermitRootLogin no`.
- UFW: 22 (или нестандартный SSH), 80, 443. Ничего другого наружу.

## Security review checklist

Перед merge крупной фичи / релизом:

- [ ] Нет открытых эндпоинтов без явной авторизации (anonymous допустим только осознанно).
- [ ] CSRF/Origin покрыт subscriber’ом для admin API.
- [ ] DTO + Validator на входе.
- [ ] Все строковые user-inputs уходят в DB через PDO bind.
- [ ] Twig output без `|raw` (или с явным safe-источником).
- [ ] Пароли — через `PasswordAuthenticatedUserInterface`, не plain text.
- [ ] Секреты не в Git.
- [ ] Логи не пишут пароли/токены/PII (`PiiRedactorProcessor`).
- [ ] Security headers активны.
- [ ] File uploads — через `UploadValidator`.
- [ ] Rate limit на критичные публичные endpoint’ы.
- [ ] Functional security tests прошли.

## Common mistakes

- Открыть `^/admin` для `IS_AUTHENTICATED_FULLY` вместо `ROLE_ADMIN`.
- Забыть `#[IsGranted]` на admin endpoint, полагаясь только на firewall (риск при перенастройке firewall’ов).
- Закомментировать `AdminApiCsrfSubscriber` для упрощения e2e — оставить так в production.
- Поставить `cost = 4` для `password_hashers` на prod.
- Перевести `login_throttling` в `test` глобально и забыть.
- Включить `web_profiler` на prod.
- Отдавать stack trace на `Throwable` в JSON.
- Оставить `Adminer` доступным извне на VPS.

## Связанные документы

- [12-admin-area](12-admin-area.md)
- [14-api-area](14-api-area.md)
- [25-files-and-uploads](25-files-and-uploads.md)
- [27-config-and-env](27-config-and-env.md)
- [37-runbooks](37-runbooks.md)
- [SECURITY.md](legacy/SECURITY.md)
- [ROLES.md](legacy/ROLES.md)
- [UPLOAD_SECURITY.md](legacy/UPLOAD_SECURITY.md)
