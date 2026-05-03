# Deploy на VPS

Staging и production разворачиваются без Docker. Docker используется только для локальной разработки.

## Release layout

```text
/var/www/zaborprofil/
├── releases/
├── shared/
│   ├── .env.local
│   ├── public_html/uploads/
│   ├── var/log/
│   └── backups/
└── current -> releases/<timestamp>
```

## Серверные компоненты

- Nginx
- PHP-FPM `>=8.5`
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

Production дополнительно делает backup database и uploads перед миграциями, проверяет созданные backup-файлы и пишет deployment log в `shared/deployments/deployments.jsonl`.

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
CONFIRM_DEPLOY_SAFETY_CHECKLIST=yes \
BRANCH=master \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://zaborprofil.ru/health \
tools/deploy/deploy-production.sh
```

Rollback:

```bash
HEALTH_URL=https://zaborprofil.ru/health tools/deploy/rollback.sh
```

Список релизов и rollback на конкретный релиз:

```bash
tools/deploy/rollback.sh --list
HEALTH_URL=https://zaborprofil.ru/health tools/deploy/rollback.sh 2026-05-03_142000
```

## Nginx

Document root должен указывать на:

```text
/var/www/zaborprofil/current/public_html
```

Все неизвестные URL должны проксироваться в `public_html/index.php`.

## Откат

Откат выполняется переключением `current` на предыдущий или явно выбранный release, перезапуском PHP-FPM/Nginx/worker и health-check.

## DevOps safety

- Deploy/rollback защищены lock directory `${APP_ROOT}/.deploy.lock`.
- Production deploy требует `CONFIRM_STAGING_DEPLOYED=yes` и `CONFIRM_DEPLOY_SAFETY_CHECKLIST=yes`.
- Backup retention управляется `BACKUP_RETENTION_DAYS` (по умолчанию 14).
- Deployment log retention управляется `DEPLOY_LOG_RETENTION_DAYS` (по умолчанию 90).
- Дополнительный staging marker можно включить через `REQUIRE_STAGING_MARKER=yes`.

## GitHub Actions

Deploy workflow использует GitHub Environments:

- `staging` — push в `develop` или `staging`.
- `production` — tag `v*` или manual workflow с approval.

Production job зависит от успешного staging job и передает оба production safety confirmations.
