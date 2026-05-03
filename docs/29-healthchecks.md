# 29. Healthchecks

## Текущее состояние

`Shared\UI\Http\HealthCheckController` — endpoint `GET /health`, отдаёт `200 OK` если приложение поднято.

Используется:

- Nginx upstream проверка.
- Deploy скриптом после переключения релиза (`tools/deploy/health-check.sh`).
- Local Docker `make health`.

## Целевое состояние

Разделить на:

- **Liveness** (`/health/live`) — приложение запущено, отвечает на HTTP.
- **Readiness** (`/health/ready`) — приложение готово принимать трафик: БД доступна, Redis доступен, миграции применены, FS writable.
- **Status** (`bin/console app:healthcheck`) — расширенная диагностика для оператора.

## Ready check (целевое)

```php
final class ReadinessController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CacheInterface $cache,
        private readonly MigrationsStatus $migrations,
        private readonly FilesystemCheck $fs,
    ) {
    }

    #[Route('/health/ready', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'db' => $this->checkDb(),
            'redis' => $this->checkRedis(),
            'migrations' => $this->migrations->upToDate(),
            'fs' => $this->fs->writable('var/cache') && $this->fs->writable('var/log'),
        ];

        $ok = !in_array(false, $checks, true);

        return new JsonResponse(['ok' => $ok, 'checks' => $checks], $ok ? 200 : 503);
    }
}
```

## Что проверять

| Проверка | Liveness | Readiness | Console status |
|---|---|---|---|
| HTTP отвечает | да | да | n/a |
| PostgreSQL connect | нет | да | да |
| Redis ping | нет | да | да |
| Миграции применены | нет | да | да |
| `var/cache` writable | нет | да | да |
| `var/log` writable | нет | да | да |
| `public_html/build/manifest.json` существует | нет | опц. | да |
| Messenger worker жив | нет | нет | да (через systemctl) |
| Disk usage < 90% | нет | нет | да |
| Memory usage / load | нет | нет | да |

## Docker healthcheck

В `docker-compose.yml`:

- `postgres` — `pg_isready`.
- `redis` — `redis-cli ping`.
- `app` — целевое: `curl -f http://localhost/health` через nginx (если nginx в той же сети, использовать `wget --spider`).
- `nginx` — целевое: `wget --spider http://localhost/health`.

## Nginx upstream health (production)

```nginx
location = /health {
    proxy_pass http://php_upstream;
    proxy_set_header Host $host;
    access_log off;
}
```

`php_upstream` — `unix:/run/php/php8.5-fpm.sock` или TCP к php-fpm.

## Что закрыть от внешнего мира

- `/health/ready` может выдавать имя сервисов и состояние — закрыть на nginx по IP (`allow 127.0.0.1; deny all;`) или вынести на отдельный nginx vhost на нестандартный порт.
- `/health/live` — можно открыть наружу, но также допустимо закрыть.
- `bin/console app:healthcheck` — только SSH.

## Console healthcheck (целевое)

```bash
php bin/console app:healthcheck --json
```

Возвращает exit code:

- `0` — всё ок;
- `1` — деградация;
- `2` — критическая ошибка.

Может вызываться из cron / monitoring.

## Symfony lint commands

В CI:

- `php bin/console doctrine:migrations:status`
- `php bin/console doctrine:schema:validate --skip-sync`
- `php bin/console lint:container`
- `php bin/console lint:twig templates`

Это статические healthcheck’и, которые ловят проблемы до runtime.

## Что НЕ должно делать healthcheck

- Запускать тяжёлые SQL.
- Зависеть от внешних сервисов (если внешний API лежит — приложение не «не готово»; это деградация, а не unready).
- Делать write-operations.
- Принимать input без validation.

## Связанные документы

- [37-runbooks](37-runbooks.md)
- [34-deployment](34-deployment.md)
- [32-docker-architecture](32-docker-architecture.md)
