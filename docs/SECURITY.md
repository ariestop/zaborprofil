# Безопасность

## Уже заложено

- Symfony Security form login.
- CSRF для login form.
- CSRF для admin JSON API через double-submit:
  - Токен `admin_api` рендерится в `templates/admin/dashboard.html.twig` как `<meta name="admin-csrf-token" content="...">`.
  - Vue admin читает токен и шлет его в заголовке `X-CSRF-Token` на каждый небезопасный запрос (POST/PUT/PATCH/DELETE) под `^/admin/api`.
  - `App\Module\Admin\Infrastructure\Http\AdminApiCsrfSubscriber` валидирует заголовок через `CsrfTokenManagerInterface` и возвращает `403`, если токен отсутствует или невалиден.
- AdminUserChecker (`App\Module\Auth\Infrastructure\Security\AdminUserChecker`) блокирует логин и активную сессию неактивного администратора.
- Login throttling (`security.firewalls.main.login_throttling`): не более 5 попыток за 15 минут на пару IP+identifier.
- RBAC: роли и права описаны в `docs/ROLES.md`.
- Admin API: state-changing запросы `/admin/api/*` требуют валидный CSRF token и same-origin `Origin`/`Referer`.
- Security headers: ответы получают `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` и `Content-Security-Policy-Report-Only`.
- Upload security: базовая политика описана в `docs/UPLOAD_SECURITY.md`.
- `X-Robots-Tag: noindex, nofollow, noarchive` на всех ответах `^/admin*` (`App\Module\Admin\Infrastructure\Http\AdminNoIndexSubscriber`).
- Admin Content API возвращает generic `Internal server error` на 500 и логирует исходное исключение через Monolog (`App\Module\Content\UI\Admin\ContentApiResponder`).
- Password hashers через Symfony.
- RBAC через роли Symfony.
- `public_html/uploads` подготовлен для будущих безопасных загрузок.

## Дальше

- Honeypot и антиспам для заявок.
- Проверка MIME-type и размера файлов.
- CSP для публичного сайта и админки (P2 — раздел `nginx-production.conf` hardening).
- AuditLog для критичных действий.
