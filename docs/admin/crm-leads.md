# CRM заявок (`/admin/crm`)

Раздел для работы менеджеров с заявками, пришедшими с публичного сайта (`POST /api/leads`).
Модуль: `src/Module/Lead`, frontend: `admin/pages/CrmPage.tsx`, `admin/pages/LeadDetailPage.tsx`, `admin/entities/lead`.

## Возможности

- **Список** с серверной пагинацией (по умолчанию 25, максимум 100 на страницу), а не «последние 100».
- **Поиск** `q`: имя, email, текст заявки, телефон. Телефон ищется по нормализованным цифрам (`phone_digits`), поэтому `8 (900) 111`, `+7900111` и `900-111` находят одну и ту же заявку.
- **Фильтры**: статус, источник (`source`), диапазон дат `from`/`to` (`YYYY-MM-DD`, `to` включительно), ответственный (`me`, `none` или id пользователя).
- **Сортировка**: `createdAt` (по умолчанию), `updatedAt`, `name`, `status`, `source`; направление `asc`/`desc`.
- **Счётчики по статусам** (`new`, `in_progress`, `done`, `spam`) учитывают активные фильтры, кроме самого фильтра статуса, поэтому вкладки статусов показывают, сколько заявок будет при выборе статуса. Состояние фильтров хранится в URL.
- **Карточка** `/admin/crm/{id}`: контакты, сообщение, согласие на обработку данных, spam score, история событий, смена статуса, ответственный, заметка менеджера.
- **Ответственный** — активный пользователь админки с ролью менеджера/администратора (`GET /admin/api/leads/assignees`).
- **Экспорт CSV** по текущим фильтрам и сортировке (до 10 000 строк).
- Dashboard использует лёгкий `GET /admin/api/leads/summary` (счётчики) вместо загрузки списка.

## API

Все ответы с ошибками имеют формат `AdminApiErrorResponder`: `{"error": "...", "code": "..."}`.

| Метод | URL | Право | Описание |
|---|---|---|---|
| GET | `/admin/api/leads` | `leads.view` | список: `items`, `total`, `page`, `perPage`, `pages`, `counts`, `statuses`, `sources` |
| GET | `/admin/api/leads/summary` | `leads.view` | `total`, `new`, `byStatus` |
| GET | `/admin/api/leads/assignees` | `leads.view` | пользователи, которых можно назначить |
| GET | `/admin/api/leads/export` | `leads.export` | CSV (`text/csv`, `Cache-Control: no-store, private`) |
| GET | `/admin/api/leads/{id}` | `leads.view` | карточка с историей `events` |
| PATCH | `/admin/api/leads/{id}/status` | `leads.manage` | `{ "status": "in_progress" }` |
| PATCH | `/admin/api/leads/{id}/assignee` | `leads.manage` | `{ "assigneeId": "<id>" \| null }` |
| POST | `/admin/api/leads/{id}/notes` | `leads.manage` | `{ "text": "..." }`, до 2000 символов, ответ `201` |

Коды ошибок: `VALIDATION` (422: неверный статус, дата, id, пустая или слишком длинная заметка, недопустимый ответственный), `NOT_FOUND` (404), `ACCESS_DENIED` (403).

## История и аудит

- Каждое изменение (статус, ответственный, заметка) сохраняется в таблице `lead_events` с автором (`actor_id`, `actor_label`) и данными события (`from`/`to`).
- Те же действия пишутся в audit log: `lead.status_changed`, `lead.assigned`, `lead.note_added` (только длина заметки, не текст), а также `lead.viewed` и `lead.exported` (количество строк и фильтры; текст поискового запроса не сохраняется — только признак `hasQuery`).
- Повторная установка того же статуса или ответственного — no-op без события.

## Защита персональных данных

- Просмотр — `leads.view`, изменения — `leads.manage`, выгрузка — отдельное право `leads.export` (по умолчанию только `ROLE_ADMIN`/`ROLE_SUPER_ADMIN`), проверяются через `AdminPermissionVoter`.
- Список не отдаёт полный текст сообщения и `consentSnapshot` (IP, User-Agent) — только превью сообщения; полные данные доступны в карточке, открытие которой фиксируется в audit log.
- В логах телефон маскируется (`PhoneNumber::mask`), `PiiRedactorProcessor` дополнительно заменяет телефоны в строках на `[phone]` и скрывает ключи `note_text`.
- CSV: ячейки, начинающиеся с `=`, `+`, `-`, `@`, табуляции или `\r`, получают префикс `'` (OWASP CSV Injection); корректные номера телефонов вида `+7 900 …` не искажаются. Файл начинается с UTF-8 BOM для Excel.

## База данных

Миграция `Version20261007141500`:

- `leads.phone_digits` (с backfill из `phone`), `leads.assignee_id`;
- индексы по `created_at`, `source`, `assignee_id`, `phone_digits`;
- таблица `lead_events` (внешний ключ на `leads` с `ON DELETE CASCADE`).

## Тесты

- PHPUnit (MySQL): `tests/Functional/Lead/LeadAdminApiTest.php`, `tests/Unit/Lead/*`, правила voter и редактора логов.
- vitest: `admin/pages/CrmPage.spec.tsx`, `admin/pages/LeadDetailPage.spec.tsx`.

## Не входит в текущую итерацию

- UTM-метки заявок и журнал доставки уведомлений (email/Telegram);
- политика хранения и анонимизация старых заявок;
- бейдж «новые заявки» в боковом меню (общий layout админки).
