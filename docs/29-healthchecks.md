# 29. Healthchecks

## Фактическое состояние

Проект использует три HTTP endpoint:

- `GET /health/live` — liveness (приложение отвечает на HTTP).
- `GET /health/ready` — readiness (приложение готово обслуживать трафик).
- `GET /health` — агрегированный статус.

`/health/ready` возвращает:

- `200 OK`, если обязательные проверки в норме;
- `503 Service Unavailable`, если хотя бы одна критичная проверка в состоянии `fail`.

## Что проверяется в readiness

- приложение;
- подключение к PostgreSQL;
- Redis-backed cache;
- writable storage (`var/cache`, `var/log`, `public_html/uploads`);
- таблица Doctrine migrations (если уже создана).

## Admin Health Center

- UI: `/admin/system/health`.
- API: `GET /admin/api/system/health`.
- В разделе отображаются статус, `APP_ENV`, `APP_DEBUG`, версия PHP, детали checks и системные предупреждения.

## Diagnostics CLI

```bash
php bin/console app:system:diagnostics
```

Команда проверяет health checks, критичные PHP extensions, env-конфигурацию и наличие Vite manifest.

## Матрица проверок

| Проверка | `/health/live` | `/health/ready` | Diagnostics CLI |
|---|---|---|---|
| HTTP отвечает | да | да | да |
| PostgreSQL connect | нет | да | да |
| Redis ping | нет | да | да |
| Миграции применены | нет | да | да |
| `var/cache` writable | нет | да | да |
| `var/log` writable | нет | да | да |
| `public_html/uploads` writable | нет | да | да |
| Vite manifest существует | нет | опц. | да |

## Использование в деплое и мониторинге

- `tools/deploy/health-check.sh` использует `/health` после переключения релиза.
- `tools/deploy/staging-smoke.sh` проверяет `/health` и `/health/ready`.
- Local Docker `make health` использует тот же health endpoint.

## Ограничения и безопасность

- Health endpoints не должны делать write-операции.
- Проверки должны быть быстрыми, без тяжелых SQL и без внешних HTTP-зависимостей.
- Детальный readiness при необходимости ограничивается на nginx по IP.

## Целевое расширение

- Единый machine-readable console endpoint (`app:healthcheck --json`) с стандартизированными exit codes для systemd/timers.

## Связанные документы

- [34-deployment](34-deployment.md)
- [37-runbooks](37-runbooks.md)
- [44-troubleshooting](44-troubleshooting.md)
