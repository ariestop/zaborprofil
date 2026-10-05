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
- `^/admin` — любая админская роль (`ROLE_EDITOR`, `ROLE_SEO`, `ROLE_MANAGER`; `ROLE_ADMIN` и `ROLE_SUPER_ADMIN` — через `role_hierarchy`). Права на разделы проверяет `AdminPermissionVoter`, меню и маршруты SPA строятся по `GET /admin/api/me`.

## RBAC

См. [20-security-and-access-control](20-security-and-access-control.md).

Права (`AdminPermission`): `pages.view`, `pages.create`, `pages.edit`, `pages.publish`, `pages.delete`, `seo.edit`, `media.upload`, `media.delete`, `leads.view`, `leads.manage`, `leads.export`, `settings.edit`, `users.manage`, `system.view`, `system.manage`.

Для опасных системных операций введено отдельное разрешение `system.dangerous`:

- `system.view` — чтение разделов System Center (доступно `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`);
- `system.manage` — стандартные mutating-операции;
- `system.dangerous` — restart/reload/clear-cache/retry-failed/remove-failed (только `ROLE_SUPER_ADMIN`).

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

### Единый формат ошибок Admin API

Все ошибки `/admin/api/*` имеют форму `{"error": "<текст>", "code": "<КОД>"}` (поле `error` читает `apiRequest` во фронтенде).

| Ситуация | Статус | `code` | Текст в `error` |
|---|---|---|---|
| `InvalidArgumentException`, `ValueError`, `ClientSafeExceptionInterface` (в т.ч. `UploadSecurityException`) | 422 | `VALIDATION` | сообщение исключения (написано для пользователя) |
| `NotFoundExceptionInterface` (`ContentNotFoundException`, `CatalogNotFoundException`) | 404 | `NOT_FOUND` | сообщение исключения |
| Битое тело запроса (`RequestExceptionInterface`, `JsonException`) | 400 | `BAD_REQUEST` | `Request body is invalid.` |
| Нет прав / CSRF / Origin | 403 | `ACCESS_DENIED` | `Access denied.` или текст проверки |
| Любое другое исключение | 500 | `INTERNAL` | `Internal server error` |

Правила:

- Контроллеры не пишут `$exception->getMessage()` в ответ. В `catch (Throwable $exception)` они вызывают `AdminApiErrorResponder::fromThrowable()` (в модуле Content — через `ContentApiResponder`); оригинал исключения логируется в канал `admin`.
- Тексты доменных исключений, которые можно показывать клиенту, помечаются `InvalidArgumentException` либо маркерами `ClientSafeExceptionInterface` / `NotFoundExceptionInterface` из `App\Shared\Domain\Exception`.
- `AdminApiExceptionSubscriber` — последний рубеж для `^/admin/api`: необработанные исключения (SQL, пути, ошибки драйвера) превращаются в тот же JSON, а `HttpException` 404/405/5xx получают нейтральный текст.
- Публичный `POST /api/leads` использует тот же responder.
Пример: см. [14-api-area](14-api-area.md).

### Маршруты Content (фактическое)

| Метод | URL | Действие |
|---|---|---|
| `GET` | `/admin/api/content/pages` | Список страниц целиком (у каждой `updatedAt`, `updatedBy` — id администратора и `updatedByName` — его имя или email, у опубликованных — `hasUnpublishedChanges`: рабочая версия отличается от опубликованной); с `page`/`perPage` (до 100) и фильтрами `q` (название или адрес), `status` — страница списка и `meta` (`total`, `page`, `perPage`, `pages`) без `hasUnpublishedChanges` |
| `POST` | `/admin/api/content/pages` | Создать страницу |
| `PUT` | `/admin/api/content/pages/{id}` | Обновить страницу |
| `POST` | `/admin/api/content/pages/{id}/publish` | Опубликовать |
| `POST` | `/admin/api/content/pages/{id}/archive` | Архивировать |
| `DELETE` | `/admin/api/content/pages/{id}` | Удалить (soft) |
| `POST` | `/admin/api/content/pages/{pageId}/blocks` | Добавить блок |
| `PUT` | `/admin/api/content/blocks/{id}` | Обновить блок |
| `DELETE` | `/admin/api/content/blocks/{id}` | Удалить блок |
| `POST` | `/admin/api/content/pages/{pageId}/blocks/reorder` | Изменить порядок |

### Маршруты Media

| Метод | URL | Действие |
|---|---|---|
| `GET` | `/admin/api/media/assets` | Список (`page`, `perPage`, `q`, `type`, `sort`; в ответе `assets` и `pagination`) |
| `POST` | `/admin/api/media/assets` | Загрузка файла (`multipart`, поле `file`) |
| `PATCH` | `/admin/api/media/assets/{id}` | Изменить `alt` и `title` |
| `DELETE` | `/admin/api/media/assets/{id}` | Удалить ассет и файлы |

Подробности: [admin/media-library](admin/media-library.md).

## Admin SPA

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md). React + TypeScript + Tailwind + Vite, билдится в `public_html/build/`. Manifest рендерится в Twig через `ViteAssetExtension`.

Редактирование страниц — единый экран с вкладками `/admin/pages/{id}[/seo|/settings|/revisions]`, создание — `/admin/pages/new`; все пути отдаёт catch-all `/admin/{path}`. Подробности: [admin/page-editor](admin/page-editor.md). `PageOutput` (`GET/PUT /admin/api/content/pages`) возвращает `parentId`.

## System разделы (фактическое состояние)

- `/admin/system` + `GET /admin/api/system/overview` — обзор системы.
- `/admin/system/processes` + `GET/POST /admin/api/system/processes*` — процессы и сервисы.
- `/admin/system/logs` + `GET /admin/api/system/logs` — системные логи.
- `/admin/system/queues` + `GET/POST /admin/api/system/queues*` — очереди Symfony Messenger.
- `/admin/system/cache` + `GET/POST /admin/api/system/cache*` — кэш.
- `/admin/system/database` + `GET /admin/api/system/database` — состояние БД.
- `/admin/system/security` + `GET /admin/api/system/security` + `POST /admin/api/system/security/confirm-token` — security posture и confirm token для опасных действий.
- `/admin/system/backups` + `GET /admin/api/system/backups` — статус бэкапов.
- `/admin/system/deploy` + `GET /admin/api/system/deploy` — deploy/release info.
- `/admin/system/audit` + `GET /admin/api/system/audit` — аудит действий админов.

Legacy endpoint аудита сохранён по `GET /admin/api/system/audit/legacy`.

### Confirm flow для опасных действий

1. UI показывает пользователю confirm-диалог.
2. После подтверждения frontend запрашивает `POST /admin/api/system/security/confirm-token` с `action`.
3. Полученный `confirmToken` отправляется в dangerous endpoint.
4. Backend валидирует токен (одноразовый, TTL, привязка к actor/action), проверяет `system.dangerous`, выполняет только whitelist-команду и пишет audit `attempt/success/failure`.

### Миграции БД из веб-админки

`POST /admin/api/settings/migrations/{version}/apply|rollback` — опасная операция (меняет схему БД), поэтому:

- по умолчанию выключена: переменная `ADMIN_WEB_MIGRATIONS_ENABLED` (по умолчанию `0`) должна быть `1`, иначе ответ `403` с `code: MIGRATIONS_DISABLED`. На production и staging оставляем `0`: миграции выполняет CLI при деплое (`doctrine:migrations:migrate`);
- нужны права `settings.edit` + `system.manage` + `system.dangerous` (фактически `ROLE_SUPER_ADMIN`); `ROLE_ADMIN` получает `403`;
- нужен одноразовый `confirmToken`, выданный через `POST /admin/api/system/security/confirm-token` для действия `migration.apply:{version}` или `migration.rollback:{version}`; токен привязан к пользователю и версии;
- каждая попытка пишет audit `system.migration.attempt` → `system.migration.success` / `system.migration.failure` (`entityType = system.migration`, `entityId = apply:{version}`);
- ошибки не раскрывают SQL и пути: доменные ограничения («уже применена», «откатывается только последняя») → `422`, неизвестная версия → `404`, остальное → `500 Internal server error` + лог в канал `admin`;
- `GET /admin/api/settings/migrations` остаётся под `settings.edit`, но `canApply` / `canRollback` равны `false`, а `actionsAllowed = false`, если действия недоступны (флаг выключен или не хватает прав); UI показывает подсказку про CLI.
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
- [14-api-area](14-api-area.md)
- [28-logging-observability](28-logging-observability.md)
- [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md)
