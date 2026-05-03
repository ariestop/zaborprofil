# Maintenance mode

Maintenance mode хранится в файле `var/maintenance.json`, чтобы режим работал
даже при проблемах Redis или PostgreSQL.

## CLI

```bash
php bin/console app:maintenance:on --message="Сайт временно недоступен" --allow-ip=127.0.0.1
php bin/console app:maintenance:status
php bin/console app:maintenance:off
```

## Админка

Раздел:

```text
/admin/system/maintenance
```

API:

```text
GET  /admin/api/system/maintenance
POST /admin/api/system/maintenance/on
POST /admin/api/system/maintenance/off
```

Во время maintenance остаются доступны:

- `/admin`;
- `/health*`;
- `/build`;
- `/uploads`.

Публичные страницы получают `503 Service Unavailable`, `Retry-After: 600` и
`X-Robots-Tag: noindex, nofollow`.
