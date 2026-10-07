# 27. Config и environment variables

## Файлы

| Файл | Что | Где |
|---|---|---|
| `.env` | Базовые значения dev | в Git |
| `.env.local` | Override для local dev | **вне Git** |
| `.env.local.example` | Шаблон для local | в Git |
| `.env.test` | Базовые значения test | в Git |
| `.env.test.local` | Override для test | вне Git |
| `.env.test.ci` | MySQL-вариант для CI | в Git |
| `.env.staging.example` | Шаблон для staging shared | в Git |
| `.env.staging.beget.example` | Шаблон `.env.local` для staging на Beget (`dev.zaborprofil.ru`), см. [49-beget-staging-deploy](49-beget-staging-deploy.md) | в Git |
| `.env.production.example` | Шаблон для production shared | в Git |
| `.env.staging` / `.env.production` | Реальные значения | **вне Git**, на VPS в `shared/` |

## Окружения

| `APP_ENV` | Где | Поведение |
|---|---|---|
| `dev` | local Docker / OSPanel | profiler, debug, verbose errors, no cache pool optimisation |
| `test` | CI, локальные тесты | array cache, in-memory messenger, low password cost |
| `staging` | staging VPS | production-like, отдельный домен, robots `Disallow: /` |
| `prod` | production VPS | оптимизированный cache, no debug, full logging |

> Staging на Beget (`dev.zaborprofil.ru`) запускается с литеральным `APP_ENV=staging` и `STAGING_AUTH_*` (см. [49-beget-staging-deploy](49-beget-staging-deploy.md)); `DATABASE_URL` — только `mysql://`, `REDIS_URL` не используется.

> Отдельных ключей конфигурации для `staging` нет: каждый блок `when@prod` в `config/` объявлен YAML-якорем и повторён как `when@staging: *prod_…`, поэтому staging собирается с prod-настройками (логирование, кэши Doctrine, запрет dev-сервера Vite). Отличия staging задаёт код по `kernel.environment`: Basic Auth (`StagingAccessSubscriber`), `noindex`, отсутствие HTTP-кэша и HSTS. Паритет проверяет `tests/Unit/Config/StagingConfigParityTest.php`.

## Обязательные переменные

| Переменная | Значение | Где |
|---|---|---|
| `APP_ENV` | `dev` / `test` / `prod` | везде |
| `APP_DEBUG` | `0` или `1` | везде |
| `APP_SECRET` | случайная строка ≥ 32 символа | везде, **уникальная per-env** |
| `DATABASE_URL` | `mysql://user:pass@host:3306/db?serverVersion=8.4&charset=utf8mb4` | везде |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` (prod) | везде |
| `MAILER_DSN` | `smtp://...` или `null://null` для dev без почты | везде |
| `SITE_URL` | `https://zaborprofil.ru` или `http://localhost` | везде |
| `DEFAULT_URI` | `https://zaborprofil.ru` | для CLI генерации URL |

## Опциональные / вспомогательные

| Переменная | Значение | Кто читает |
|---|---|---|
| `APP_SHARE_DIR` | `/var/www/zaborprofil/shared` (prod) или `var/share` (dev) | целевое: `FileStorage` |
| `TRUSTED_PROXIES` | список IP nginx/proxy | Symfony framework |
| `TRUSTED_HOSTS` | regex hostname | Symfony framework |
| `STAGING_AUTH_ENABLED` | `1` включает Basic Auth на `APP_ENV=staging` (по умолчанию выключено) | `StagingAccessSubscriber` |
| `STAGING_AUTH_USER` / `STAGING_AUTH_HASH` | логин и bcrypt-хеш пароля; хеш в `.env.local` в **одинарных кавычках**. При `STAGING_AUTH_ENABLED=1` и неверных значениях доступ закрыт для всех | `StagingAccessSubscriber` |
| `STAGING_ALLOW_PUBLIC` | `1` вместе с `STAGING_AUTH_ENABLED=0` разрешает деплой временно открытого staging без Basic Auth; без явного `1` preflight `deploy-beget.sh` требует `STAGING_AUTH_ENABLED=1`. Приложение флаг не читает; noindex действует всегда ([49-beget-staging-deploy](49-beget-staging-deploy.md)) | `tools/deploy/deploy-beget.sh` |
| `ADMIN_WEB_MIGRATIONS_ENABLED` | `0` (по умолчанию) / `1` — разрешить apply/rollback Doctrine-миграций из веб-админки (только `ROLE_SUPER_ADMIN` + confirm-token). На staging/production держать `0` | `MigrationsApiController` |
| `ADMIN_WEB_ASSET_BUILD_ENABLED` | `0` (по умолчанию; в `dev` — `1`) / `1` — разрешить `npm run build` из веб-админки (`system.manage`). Выключено: `403` с `code: ASSET_BUILD_DISABLED`, виджет сборки скрыт. На staging/production держать `0`: ассеты собирает деплой | `AssetBuildApiController` |
| `PUBLIC_HTTP_CACHE_ENABLED` | `1` — публичные `Cache-Control`/`ETag` для страниц сайта (по умолчанию `1` в `prod`, `0` иначе; на `staging` не действует) | `PublicPageHttpCache` |
| `PUBLIC_HTTP_CACHE_MAX_AGE`, `PUBLIC_HTTP_CACHE_S_MAXAGE`, `PUBLIC_HTTP_CACHE_STALE_WHILE_REVALIDATE` | TTL браузера / общих кэшей / stale-while-revalidate, секунды (`0` / `300` / `60`) | `PublicPageHttpCache` |
| `NGINX_FASTCGI_CACHE_DIR`, `NGINX_FASTCGI_CACHE_LEVELS` | Каталог и `levels` nginx `fastcgi_cache_path` для сброса кэша при публикации (пусто — отключено, `1:2`) | `NginxFastcgiCachePurger` |
| `XDEBUG_MODE` | `off` / `develop,debug` | Docker PHP-FPM |
| `MYSQL_DATABASE`/`MYSQL_USER`/`MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD`/`MYSQL_PORT` | для Docker Compose | Compose |
| `HTTP_PORT` | хост-порт nginx для проброса `host:container` (Compose). Можно задать привязку к интерфейсу: `8081` (все интерфейсы) или `127.0.0.1:8081` (только loopback хоста; удобно на сервере, для доступа с ноутбука — SSH `-L` / Remote Ports) | Compose |
| `MAILPIT_PORT`/`ADMINER_PORT`/`VITE_PORT` | хост-порты | Compose, dev only |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | для critical alerts; читаются через контейнер (`.env.local` или `secrets:set`), отправляет worker `messenger:consume async` | `TelegramErrorHandler`, `SendTelegramLogMessageHandler` |
| `RELEASE_TAG` | версия для логов/Sentry | `ReleaseProcessor` |
| `LEAD_NOTIFICATION_EMAIL` / `LEAD_TELEGRAM_BOT_TOKEN` / `LEAD_TELEGRAM_CHAT_ID` | куда уходят уведомления о новых заявках; если доставка не удалась ни по одному каналу, пишется `error` в канал `observability` (алерт) | `LeadNotifier` |
| `SENTRY_DSN` | DSN Sentry или self-hosted (GlitchTip); пусто — error tracking выключен. Персональные данные не отправляются ([28-logging-observability](28-logging-observability.md)) | `sentry/sentry-symfony` |
| `SENTRY_RELEASE` | необязательная метка релиза в Sentry | `sentry/sentry-symfony` |

## Deploy script variables (операционные)

Переменные, которые читает `tools/deploy/*.sh`:

- `APP_ROOT`, `REPOSITORY`, `BRANCH`, `HEALTH_URL`, `KEEP_RELEASES`.
- `PHP_BIN`, `COMPOSER_BIN`, `NPM_BIN`.
- `PHP_FPM_SERVICE`, `NGINX_SERVICE`, `WORKER_SERVICE`.
- `HEALTH_RETRIES`, `HEALTH_SLEEP_SECONDS`.
- Production guards: `CONFIRM_STAGING_DEPLOYED=yes`, `CONFIRM_DEPLOY_SAFETY_CHECKLIST=yes`.
- Дополнительно: `DATABASE_BACKUP_COMMAND` (кастомная backup команда).

## Default values

Базовый `.env` содержит безопасные dev-значения. **Никогда не коммитить** реальные production-значения в `.env`. На production значения только в `shared/.env.local`.

## Startup validation (целевое)

Создать `App\Shared\Application\EnvCheck::run()` или Symfony `EnvVar` constraints, которые на boot проверяют наличие и формат критичных переменных. Падать рано (`fail fast`), а не отдавать 500 в случайной точке.

## Fail-fast

При отсутствии `APP_SECRET`, `DATABASE_URL` приложение должно отказаться стартовать.

## Symfony Secrets (целевое)

Symfony Secrets Vault:

```bash
php bin/console secrets:set TELEGRAM_BOT_TOKEN
```

Шифрует секрет с помощью prod private key (хранится отдельно). На VPS — только public key. Это безопаснее, чем `.env.production`.

## Что нельзя

- Коммитить `.env.local` / `.env.production` / `.env.staging` (защищено `.gitignore`).
- Использовать одинаковый `APP_SECRET` на dev и prod.
- Передавать секреты через CLI args (попадают в `ps`).
- Логировать `DATABASE_URL` целиком (включает пароль) — логировать только `host`.
- Хранить секреты в Docker `image` (только в env / runtime).

## Trusted proxies / hosts

Если перед Symfony стоит nginx/CDN — указать:

```dotenv
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
TRUSTED_HOSTS='^(zaborprofil\.ru|staging\.zaborprofil\.ru)$'
```

## Чек-лист добавления новой env переменной

- [ ] Добавлено значение по умолчанию в `.env`.
- [ ] Шаблон обновлён: `.env.local.example`, `.env.staging.example`, `.env.production.example`.
- [ ] Документировано в [34-deployment](34-deployment.md) и в этом файле.
- [ ] При обязательности — добавлена startup validation.
- [ ] При секретности — НЕ коммитить реальное значение, обновить deploy notes.
- [ ] CI обновлён (если переменная нужна в тестах) — `.env.test.ci`.
- [ ] `tools/deploy/shared-env-example.sh` обновлён, если переменная нужна на VPS.

## Связанные документы

- [33-local-development](33-local-development.md)
- [34-deployment](34-deployment.md)
- [33-local-development](33-local-development.md)
- [20-security-and-access-control](20-security-and-access-control.md)
