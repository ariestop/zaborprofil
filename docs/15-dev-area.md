# 15. Dev area

Dev-инструменты доступны **только** в `APP_ENV=dev` или `APP_ENV=test`. На production они должны быть полностью выключены.

## Что входит в dev area

| Инструмент | Условие | Префикс |
|---|---|---|
| Symfony Profiler | `dev`, `test` | `^/_profiler` |
| Web Debug Toolbar | `dev` | `^/_wdt` |
| Symfony Debug Bundle | `dev`, `test` | n/a (Errors page) |
| Adminer (Docker) | local only | `http://localhost:8080` |
| Mailpit (Docker) | local only | `http://localhost:8025` |
| Vite dev server | local only | `http://localhost:5173` |

## Bundles в `config/bundles.php`

```php
return [
    // ...
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class            => ['dev' => true, 'test' => true],
    // ...
];
```

Запрещено добавлять профайлер в `prod` ключ.

## Routes для профайлера

`config/routes/dev/`:

- `web_profiler.xml`
- `wdt.xml`

В `prod` эти файлы не загружаются.

## Firewall

В `config/packages/security.yaml`:

```yaml
firewalls:
    dev:
        pattern: ^/(_(profiler|wdt)|css|images|js|build)/
        security: false
```

Без security, потому что доступ должен быть закрыт через `APP_ENV=prod` (404 на профайлер).

## Что НЕЛЬЗЯ

- Включать `web_profiler` в production.
- Дать доступ извне к Adminer/Mailpit (даже с auth) — это дополнительная атак-поверхность.
- Хранить production-секреты в `.env.dev`/`.env.test`.
- Делать «dev контроллеры» в `src/Controller/Dev/` без явного env-guard. Если уж нужен debug-endpoint, проверять `if (!in_array($_SERVER['APP_ENV'] ?? '', ['dev', 'test'], true)) { throw 404 }`.

## Test environment

`APP_ENV=test` дополнительно:

- `cache.adapter.array` для всех пулов;
- `messenger`-`async/failed` → `in-memory://`;
- `password_hashers` cost понижается для скорости;
- `login_throttling` отключён.

## Vite dev server

Локально через Docker — `make npm-dev` поднимает Vite на `http://localhost:5173`. `ViteAssetExtension` сам отдаёт ссылки на dev server, если `manifest.json` отсутствует или env=dev.

## Безопасность dev area

- Dev-инструменты **никогда** не должны быть видны на staging/production. Если на staging нужен профайлер — это отдельный осмысленный кейс с access control, и он **не** включён по умолчанию.
- Adminer/Mailpit на VPS не разворачиваются.
- Симптом проблемы: на production открывается `/...` и виден `web_profiler`. Действие: немедленно проверить `APP_ENV` в `.env.local` на VPS, отключить, перезапустить PHP-FPM.

## Связанные документы

- [27-config-and-env](27-config-and-env.md)
- [32-docker-architecture](32-docker-architecture.md)
- [33-local-development](33-local-development.md)
- [20-security-and-access-control](20-security-and-access-control.md)
