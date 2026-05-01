# Deploy на VPS

Staging и production разворачиваются без Docker. Docker используется только для локальной разработки.

## Release layout

```text
/var/www/zaborprofil/
├── releases/
├── shared/
│   ├── .env.local
│   ├── public/uploads/
│   └── var/log/
└── current -> releases/current-release
```

## Серверные компоненты

- Nginx
- PHP-FPM `>=8.4`
- PostgreSQL `>=18`
- Redis
- systemd worker для Symfony Messenger
- SSL-сертификат

## Общий порядок релиза

1. Создать новый каталог в `releases/`.
2. Получить код через Git.
3. Подключить shared-файлы и каталоги: `.env.local`, `public_html/uploads`, `var/log`.
4. Выполнить `composer install --no-dev --optimize-autoloader`.
5. Выполнить `npm ci` и `npm run build`.
6. Выполнить миграции Doctrine.
7. Прогреть cache.
8. Переключить symlink `current`.
9. Перезапустить PHP-FPM, Nginx и messenger worker.
10. Выполнить health-check.
11. Если health-check не прошел — выполнить rollback.

Production дополнительно делает backup database и uploads перед миграциями.

## Скрипты

Staging:

```bash
BRANCH=staging \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://staging.zaborprofil.ru/health \
tools/deploy/deploy-staging.sh
```

Production:

```bash
CONFIRM_STAGING_DEPLOYED=yes \
BRANCH=master \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://zaborprofil.ru/health \
tools/deploy/deploy-production.sh
```

Rollback:

```bash
HEALTH_URL=https://zaborprofil.ru/health tools/deploy/rollback.sh
```

## Nginx

Document root должен указывать на:

```text
/var/www/zaborprofil/current/public_html
```

Все неизвестные URL должны проксироваться в `public_html/index.php`.

## Откат

Откат выполняется переключением `current` на предыдущий release, перезапуском PHP-FPM/Nginx/worker и health-check.

## GitHub Actions

Deploy workflow использует GitHub Environments:

- `staging` — push в `develop` или `staging`.
- `production` — tag `v*` или manual workflow с approval.

Production job зависит от успешного staging job.
