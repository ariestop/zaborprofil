# Release readiness

Этот документ фиксирует операционные проверки перед публичным запуском. Реальные
значения VPS unit names, путей и retention здесь намеренно не фиксируются.

## Staging smoke deploy

Первый staging deploy запускается через GitHub Actions `Deploy`:

- push в `develop` или `staging`;
- либо ручной `workflow_dispatch`.

После успешного переключения релиза `tools/deploy/deploy-staging.sh` по умолчанию
запускает `tools/deploy/staging-smoke.sh`.

Что проверяет smoke:

- `/health`;
- `/health/ready`;
- `/sitemap.xml`;
- `/robots.txt`;
- `/admin/login`;
- `php bin/console app:smoke:test --env=staging`.

Если staging закрыт basic auth, передайте `BASIC_AUTH=user:password` в окружение
деплоя.

Для ручного запуска на VPS:

```bash
APP_ENV=staging \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://staging.zaborprofil.ru/health \
tools/deploy/staging-smoke.sh
```

## GitHub Environments

Workflow уже использует environments `staging` и `production`.

Минимально нужны secrets:

- `STAGING_SSH_HOST`, `STAGING_SSH_USER`, `STAGING_SSH_KEY`, `STAGING_SSH_PORT`;
- `PRODUCTION_SSH_HOST`, `PRODUCTION_SSH_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_SSH_PORT`.

Production environment должен требовать manual approval. Secrets приложения не
кладутся в GitHub Actions: они остаются на VPS в `shared/.env.local`.

## Restore rehearsal

Скрипт `tools/deploy/restore-rehearsal.sh` проверяет, что PostgreSQL dump и
uploads archive реально восстанавливаются.

Пример:

```bash
CONFIRM_RESTORE_REHEARSAL=yes \
APP_ROOT=/var/www/zaborprofil \
tools/deploy/restore-rehearsal.sh \
  --db-backup /var/www/zaborprofil/shared/backups/db/<backup>.dump \
  --uploads-backup /var/www/zaborprofil/shared/backups/uploads/<backup>.tar.gz
```

Скрипт создаёт временную БД, восстанавливает dump, проверяет наличие public
tables и удаляет временную БД после успешной проверки. Для ручной инспекции
добавьте `--keep-db`.

## Monitoring, log rotation, alerting

Репозиторий содержит:

- `tools/deploy/monitoring-check.sh` — health/readiness, disk, systemd services,
  PostgreSQL, Redis и `app:smoke:test`;
- `tools/deploy/templates/zaborprofil-monitoring.service`;
- `tools/deploy/templates/zaborprofil-monitoring.timer`;
- `tools/deploy/templates/zaborprofil-logrotate.conf`.

Установка шаблонов на VPS:

```bash
sudo cp tools/deploy/templates/zaborprofil-monitoring.service /etc/systemd/system/
sudo cp tools/deploy/templates/zaborprofil-monitoring.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now zaborprofil-monitoring.timer

sudo cp tools/deploy/templates/zaborprofil-logrotate.conf /etc/logrotate.d/zaborprofil
sudo logrotate -d /etc/logrotate.d/zaborprofil
```

Для алертов можно использовать один из вариантов:

- `ALERT_WEBHOOK_URL`;
- `ALERT_TELEGRAM_BOT_TOKEN` + `ALERT_TELEGRAM_CHAT_ID`.

Эти значения хранятся на VPS, например в
`/var/www/zaborprofil/shared/monitoring.env`.
