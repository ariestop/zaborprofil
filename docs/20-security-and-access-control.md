# 20. Безопасность и контроль доступа

Разделяем на: **AuthN** (кто), **AuthZ** (что можно), **Transport security** (как защищены каналы), **Application hardening** (как защищён код).

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
- { path: ^/admin, roles: [ROLE_EDITOR, ROLE_SEO, ROLE_MANAGER] }
```

В `/admin` входит любая админская роль: `ROLE_ADMIN` и `ROLE_SUPER_ADMIN` попадают под правило через `role_hierarchy`. `AdminUser::getRoles()` возвращает только назначенные роли (раньше всегда добавлялась `ROLE_ADMIN`, из-за чего редактор, SEO и менеджер фактически были администраторами). Аккаунт без админской роли не может войти: `AdminUserChecker` отклоняет вход с сообщением «У учётной записи нет доступа к админ-панели», а уже открытая сессия получает `403`.

Конкретные разделы и эндпоинты закрывает `AdminPermissionVoter`: каждый контроллер `/admin/api/*` проверяет своё право.

### Права для SPA

`GET /admin/api/me` возвращает `{email, roles, permissions}`; те же `roles` и `permissions` шаблон `admin/dashboard.html.twig` отдаёт в `data-roles` / `data-permissions` корня `#admin-app`. Список прав вычисляет `AdminAccessProfile` теми же voter'ами, что защищают эндпоинты. Фронтенд (`admin/stores/auth.ts`) использует его, чтобы:

- скрывать пункты бокового меню и командной палитры (`permission` в `admin/routes/route-config.ts`);
- закрывать маршруты компонентом `RouteGuard` (страница 403 вместо раздела);
- прятать кнопки и вкладки (создание страницы, публикация, вкладки редактора, удаление медиа, экспорт заявок).

Это только удобство интерфейса: сервер проверяет право на каждый запрос. Список прав в `admin/entities/user/permissions.ts` должен совпадать с `AdminPermission` — это проверяет `permissions.spec.ts`.

### Что доступно ролям

| Роль | Разделы админки |
|---|---|
| `ROLE_EDITOR` | сводка, страницы (создание, правка блоков, отправка на ревью), медиатека (загрузка) |
| `ROLE_SEO` | сводка, страницы (просмотр, согласование), SEO: редиректы, robots, 404, аудит |
| `ROLE_MANAGER` | сводка, заявки (просмотр, статусы, ответственные, заметки; без экспорта) |
| `ROLE_ADMIN` | всё, кроме опасных операций системного центра, пользователи, настройки |
| `ROLE_SUPER_ADMIN` | всё, включая `system.dangerous` |

Системный центр (`system.*`), настройки, пользователи и журнал действий недоступны ролям ниже `ROLE_ADMIN`.

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
| `leads.export` | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` (выгрузка персональных данных в CSV, каждый экспорт пишется в audit log) |
| `settings.edit`, `users.manage`, `system.manage` | `ROLE_ADMIN` / `ROLE_SUPER_ADMIN` |

При появлении сложных правил на конкретные сущности — выделить **resource voters** (например, `PageVoter::canEdit($page, $user)`).

### Полный список прав (`AdminPermission`)

- `pages.view`
- `pages.create`
- `pages.edit`
- `pages.publish`
- `pages.submit_review`
- `pages.approve`
- `pages.unpublish`
- `pages.schedule`
- `pages.archive`
- `pages.view_revisions`
- `pages.rollback_revision`
- `pages.manage_templates`
- `pages.delete`
- `blocks.create`
- `blocks.edit`
- `blocks.delete`
- `blocks.reorder`
- `blocks.clone`
- `seo.edit`
- `seo.approve`
- `media.upload`
- `media.delete`
- `leads.view`
- `leads.manage`
- `leads.export`
- `settings.edit`
- `users.manage`
- `system.view`
- `system.manage`

## CSRF

- Login form — Symfony `enable_csrf: true`.
- Logout — `enable_csrf: true`.
- Для неудачных входов на `admin_login` логируется диагностическое событие `Admin login rejected...` с контекстом `Origin/Referer/Host`, чтобы быстрее находить проблемы same-origin и reverse proxy.
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
- Storage — native PHP sessions в файлах `var/sessions/<env>` (`framework.session.save_path`); каталог вне web root, права только для пользователя PHP-FPM. При горизонтальном масштабировании потребуется общий FS или перенос сессий в БД (`PdoSessionHandler`).

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

См. [25-files-and-uploads](25-files-and-uploads.md).

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
- Admin area дополнительно получает `X-Robots-Tag: noindex, nofollow, noarchive`.

## Nginx hardening

- HTTPS-only (`return 301 https` для `:80`).
- HSTS (после стабильного prod): `Strict-Transport-Security: max-age=...; includeSubDomains; preload`.
- TLS 1.2+, отключённый SSLv3/TLS 1.0/1.1.
- Не показывать `server_tokens`.

## Firewall и сеть

- MySQL слушает только `localhost` (`bind-address = 127.0.0.1`), приложение подключается отдельным пользователем с правами только на свою БД (без `FILE`, `SUPER`, глобальных привилегий).
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

- Вернуть принудительное добавление `ROLE_ADMIN` в `AdminUser::getRoles()` или сузить `^/admin` до `ROLE_ADMIN`: роли Editor/SEO/Manager перестанут входить в админку (или получат лишние права).
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
- [25-files-and-uploads](25-files-and-uploads.md)
