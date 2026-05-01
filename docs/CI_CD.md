# CI/CD

CI запускается на каждый push и pull request.

## CI checks

Backend job:

- `composer validate --strict`
- `composer install`
- `composer audit`
- PHP syntax check
- PHP-CS-Fixer dry-run
- PHPStan
- Rector dry-run
- Doctrine migrations status
- PHPUnit

Frontend job:

- `npm ci`
- `npm audit --audit-level=high`
- `npm run build`

## Deploy pipeline

Deploy workflow находится в `.github/workflows/deploy.yml`.

Staging:

- push в `develop` или `staging`;
- manual workflow dispatch без параметров;
- environment `staging`;
- запуск `tools/deploy/deploy-staging.sh` на VPS по SSH.

Production:

- tag `v*`;
- environment `production` должен иметь manual approval в GitHub;
- job зависит от успешного `deploy-staging`;
- запуск `tools/deploy/deploy-production.sh` с `CONFIRM_STAGING_DEPLOYED=yes`.

Production intentionally does not run from manual dispatch. Для production создается tag `v*`; workflow сначала деплоит этот tag на staging, затем после approval деплоит тот же tag на production.

## GitHub secrets

Для staging:

```text
STAGING_SSH_HOST
STAGING_SSH_USER
STAGING_SSH_KEY
STAGING_SSH_PORT
```

Для production:

```text
PRODUCTION_SSH_HOST
PRODUCTION_SSH_USER
PRODUCTION_SSH_KEY
PRODUCTION_SSH_PORT
```

Секреты приложения не хранятся в GitHub Actions. Они лежат на VPS в `/var/www/zaborprofil/shared/.env.local`.

## Первый deploy

На VPS заранее создайте shared layout:

```bash
sudo mkdir -p /var/www/zaborprofil/releases
sudo mkdir -p /var/www/zaborprofil/shared/public_html/uploads
sudo mkdir -p /var/www/zaborprofil/shared/var/log
sudo mkdir -p /var/www/zaborprofil/shared/backups
sudo chown -R www-data:www-data /var/www/zaborprofil
```

Создайте `/var/www/zaborprofil/shared/.env.local` по шаблону `.env.staging.example` или `.env.production.example`.

## Security

- Production deploy не стартует без успешного staging job.
- Production deploy делает backup перед миграциями.
- Health-check обязателен после переключения релиза.
- Rollback выполняется автоматически при failed health-check.
- Staging рекомендуется закрыть basic auth или IP allowlist.
