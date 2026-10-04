# User

Модуль пользователей админки: сущность `AdminUser` для Symfony Security и управление учётными записями.

- `Domain/AdminRole` — белый список ролей (`ROLE_SUPER_ADMIN`, `ROLE_ADMIN`, `ROLE_EDITOR`, `ROLE_SEO`, `ROLE_MANAGER`).
- `Application/Service/AdminUserService` — создание, смена пароля, роли, активация и удаление с защитой от потери доступа (self-lockout, последний администратор/суперадминистратор).
- `UI/Admin/UserApiController` — REST API `/admin/api/users`.
- `UI/Console` — команды `app:user:create-admin` и `app:user:change-password`.

Изменения `AdminUser` пишутся в журнал аудита (`AuditLogSubscriber`), хеш пароля маскируется.
