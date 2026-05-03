# Health checks

CMS имеет три публичных endpoint для мониторинга:

- `GET /health/live` — приложение отвечает, без внешних зависимостей.
- `GET /health/ready` — приложение готово принимать трафик.
- `GET /health` — агрегированный статус всех проверок.

## Readiness checks

В readiness входят:

- приложение;
- подключение к БД;
- Redis-backed cache;
- writable storage (`var/cache`, `var/log`, `public_html/uploads`);
- таблица Doctrine migrations, если она уже создана.

`/health/ready` возвращает:

- `200 OK`, если обязательные проверки здоровы;
- `503 Service Unavailable`, если есть `fail`.

## Health Center

Админка содержит раздел:

```text
/admin/system/health
```

Он получает данные из:

```text
GET /admin/api/system/health
```

API показывает:

- общий статус;
- APP_ENV и APP_DEBUG;
- версию PHP;
- platform БД;
- результаты всех checks;
- системные предупреждения.

## Diagnostics CLI

Команда:

```bash
php bin/console app:system:diagnostics
```

Проверяет health checks, важные PHP extensions, APP_ENV/APP_DEBUG и наличие Vite manifest.
