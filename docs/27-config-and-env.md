# 27. Config и environment variables

## Файлы

| Файл | Что | Где |
|---|---|---|
| `.env` | Базовые значения dev | в Git |
| `.env.local` | Override для local dev | **вне Git** |
| `.env.local.example` | Шаблон для local | в Git |
| `.env.test` | Базовые значения test | в Git |
| `.env.test.local` | Override для test | вне Git |
| `.env.test.ci` | Postgres-вариант для CI | в Git |
| `.env.staging.example` | Шаблон для staging shared | в Git |
| `.env.production.example` | Шаблон для production shared | в Git |
| `.env.staging` / `.env.production` | Реальные значения | **вне Git**, на VPS в `shared/` |

## Окружения

| `APP_ENV` | Где | Поведение |
|---|---|---|
| `dev` | local Docker / OSPanel | profiler, debug, verbose errors, no cache pool optimisation |
| `test` | CI, локальные тесты | array cache, in-memory messenger, low password cost |
| `staging` | staging VPS | production-like, отдельный домен, robots `Disallow: /` |
| `prod` | production VPS | оптимизированный cache, no debug, full logging |

> Symfony знает только `dev`/`test`/`prod` как ключи. `staging` — это `APP_ENV=prod` + отдельная инфраструктура и `.env.staging` в shared.

## Обязательные переменные

| Переменная | Значение | Где |
|---|---|---|
| `APP_ENV` | `dev` / `test` / `prod` | везде |
| `APP_DEBUG` | `0` или `1` | везде |
| `APP_SECRET` | случайная строка ≥ 32 символа | везде, **уникальная per-env** |
| `DATABASE_URL` | `postgresql://user:pass@host:5432/db?serverVersion=18&charset=utf8` | везде |
| `REDIS_URL` | `redis://host:6379` или `redis://:pass@host:6379/0` | везде |
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
| `XDEBUG_MODE` | `off` / `develop,debug` | Docker PHP-FPM |
| `POSTGRES_DB`/`USER`/`PASSWORD` | для Docker Compose | Compose |
| `HTTP_PORT` | хост-порт nginx для проброса `host:container` (Compose). Можно задать привязку к интерфейсу: `8081` (все интерфейсы) или `127.0.0.1:8081` (только loopback хоста; удобно на сервере, для доступа с ноутбука — SSH `-L` / Remote Ports) | Compose |
| `MAILPIT_PORT`/`ADMINER_PORT`/`VITE_PORT` | хост-порты | Compose, dev only |
| `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` | для critical alerts | `TelegramErrorHandler` |
| `RELEASE_TAG` | версия для логов/Sentry | `ReleaseProcessor` |

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

При отсутствии `APP_SECRET`, `DATABASE_URL`, `REDIS_URL` приложение должно отказаться стартовать.

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
