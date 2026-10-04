# Auth

Модуль авторизации. На первом этапе содержит form login для `/admin/login`.
Права и вход в админку описаны в [docs/20-security-and-access-control.md](../../../docs/20-security-and-access-control.md): `GET /admin/api/me`, `AdminAccessProfile`, проверка роли при входе (`AdminUserChecker`).
