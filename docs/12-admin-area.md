# 12. Admin area

## Префикс и назначение

| Префикс | Что отвечает |
|---|---|
| `^/admin/login` | страница логина (`SecurityController` Auth-модуля) |
| `^/admin` (HTML) | admin shell (`DashboardController`), React admin SPA |
| `^/admin/api` | admin JSON API (Content, Settings, Seo и т.д.) |

## Firewall

`config/packages/security.yaml`:

- провайдер `admin_user_provider` (`AdminUser` по `email`);
- form login (`admin_login` route);
- logout (`admin_logout` route);
- `login_throttling`: 5 попыток / 15 минут;
- `user_checker: AdminUserChecker` (блокирует неактивных).

`access_control`:

- `^/admin/login$` — `PUBLIC_ACCESS`;
- `^/admin` — `ROLE_ADMIN`.

## RBAC

См. [ROLES.md](legacy/ROLES.md) и [20-security-and-access-control](20-security-and-access-control.md).

Права (`AdminPermission`): `pages.view`, `pages.create`, `pages.edit`, `pages.publish`, `pages.delete`, `seo.edit`, `media.upload`, `media.delete`, `leads.view`, `leads.manage`, `settings.edit`, `users.manage`, `system.view`, `system.manage`.

Проверка прав — централизованная через `AdminPermissionVoter`. В контроллере:

```php
#[IsGranted(AdminPermission::PagesPublish->value)]
```

## CSRF

Admin API использует **double-submit CSRF**:

1. `DashboardController` рендерит токен `admin_api` в `<meta name="admin-csrf-token" content="...">`.
2. React SPA читает meta, шлёт в каждом небезопасном запросе как заголовок `X-CSRF-Token`.
3. `AdminApiCsrfSubscriber` валидирует токен. На несовпадение — `403`.

## Origin / Referer

`AdminApiOriginSubscriber` сравнивает `Origin`/`Referer` с `SITE_URL`. Cross-origin POST/PUT/PATCH/DELETE отвергаются.

## NoIndex

`AdminNoIndexSubscriber` добавляет `X-Robots-Tag: noindex, nofollow, noarchive` ко всем ответам `^/admin*`.

## Admin API: контракты

- `Content-Type: application/json`.
- Запрос — JSON с DTO-структурой; парсится через `JsonRequest::parse(...)`.
- Ответ — JSON через `ContentApiResponder` (унифицированный success/error).
- Ошибки 500 — заменяются на `Internal server error` с логированием оригинала в канал `admin`.

Пример: см. [CONTENT_ENGINE.md](legacy/CONTENT_ENGINE.md).

### Маршруты Content (фактическое)

| Метод | URL | Действие |
|---|---|---|
| `POST` | `/admin/api/content/pages` | Создать страницу |
| `PUT` | `/admin/api/content/pages/{id}` | Обновить страницу |
| `POST` | `/admin/api/content/pages/{id}/publish` | Опубликовать |
| `POST` | `/admin/api/content/pages/{id}/archive` | Архивировать |
| `DELETE` | `/admin/api/content/pages/{id}` | Удалить (soft) |
| `POST` | `/admin/api/content/pages/{pageId}/blocks` | Добавить блок |
| `PUT` | `/admin/api/content/blocks/{id}` | Обновить блок |
| `DELETE` | `/admin/api/content/blocks/{id}` | Удалить блок |
| `POST` | `/admin/api/content/pages/{pageId}/blocks/reorder` | Изменить порядок |

## Admin SPA

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md). React + TypeScript + Tailwind + Vite, билдится в `public_html/build/`. Manifest рендерится в Twig через `ViteAssetExtension`.

## Что НЕЛЬЗЯ в admin area

- Открыть admin без CSRF и без Origin-check — это уязвимость.
- Дать `ROLE_USER` через тот же firewall — admin firewall только для админов.
- Возвращать stack trace на ошибках — даже в `dev` отдаём generic, но логируем оригинал.
- Возвращать Doctrine entity напрямую — только через DTO.
- Хранить JWT в `localStorage` (целевое: cookie + CSRF, как сейчас).

## Чек-лист admin endpoint

- [ ] Префикс `/admin/api/...` или `/admin/...` соблюдён.
- [ ] `#[IsGranted(...)]` указан с явным разрешением.
- [ ] CSRF/Origin покрыт subscriber’ами (включён по факту префикса).
- [ ] DTO + Validator на входе.
- [ ] Application handler вызван.
- [ ] Ответ через `ContentApiResponder`.
- [ ] Логирование канал `admin`.
- [ ] Functional-тест `tests/Functional/...`.

## Связанные документы

- [08-controller-architecture](08-controller-architecture.md)
- [20-security-and-access-control](20-security-and-access-control.md)
- [ROLES.md](legacy/ROLES.md)
- [SECURITY.md](legacy/SECURITY.md)
- [CONTENT_ENGINE.md](legacy/CONTENT_ENGINE.md)
- [ADMIN_FRONTEND.md](legacy/ADMIN_FRONTEND.md)
