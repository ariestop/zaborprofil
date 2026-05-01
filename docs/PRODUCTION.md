# Production

Production разворачивается только после успешного staging deploy. Docker не является production-зависимостью.

## Stack

- Nginx `>=1.30.0`.
- PHP-FPM `>=8.5`.
- Node.js `>=25.9.0` (npm `>=11.12.1`) для сборки frontend на VPS.
- PostgreSQL `>=18` или ближайшая стабильная версия.
- Redis `>=8` с authentication и отдельной production DB.
- systemd workers для Symfony Messenger.
- SSL для `zaborprofil.ru`.
- Release-based deploy через Git.
- Backup database и uploads перед миграциями.
- Health-check после переключения релиза.

## Обязательные правила

- Не хранить production secrets в Git.
- Не использовать staging database в production.
- Не запускать production deploy без успешного staging.
- Не запускать production deploy без backup.
- Не переключать релиз без health-check.
- Держать rollback готовым перед миграциями.

## Shared env

Файл `/var/www/zaborprofil/shared/.env.local` создается вручную на VPS.

Минимум:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=unique-production-secret
APP_SHARE_DIR=/var/www/zaborprofil/shared
DATABASE_URL="postgresql://zaborprofil:password@127.0.0.1:5432/zaborprofil?serverVersion=18&charset=utf8"
REDIS_URL="redis://:password@127.0.0.1:6379/0"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=smtp://127.0.0.1:25
SITE_URL="https://zaborprofil.ru"
DEFAULT_URI="https://zaborprofil.ru"
```

## Deploy

Ручной запуск:

```bash
CONFIRM_STAGING_DEPLOYED=yes \
BRANCH=master \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://zaborprofil.ru/health \
tools/deploy/deploy-production.sh
```

Скрипт выполняет backup database и uploads перед миграциями, затем переключает `current` только после подготовки релиза.

## Nginx и systemd templates

В репозитории есть production templates:

- `tools/deploy/templates/nginx-production.conf`
- `tools/deploy/templates/zaborprofil-messenger.service`

Установка на VPS:

```bash
sudo cp tools/deploy/templates/nginx-production.conf /etc/nginx/sites-available/zaborprofil.conf
sudo ln -sfn /etc/nginx/sites-available/zaborprofil.conf /etc/nginx/sites-enabled/zaborprofil.conf
sudo nginx -t
sudo systemctl reload nginx

sudo cp tools/deploy/templates/zaborprofil-messenger.service /etc/systemd/system/zaborprofil-messenger.service
sudo systemctl daemon-reload
sudo systemctl enable --now zaborprofil-messenger.service
```

## Rollback

```bash
HEALTH_URL=https://zaborprofil.ru/health tools/deploy/rollback.sh
```

Можно указать конкретный release:

```bash
HEALTH_URL=https://zaborprofil.ru/health tools/deploy/rollback.sh /var/www/zaborprofil/releases/2026-05-01_130000
```

Rollback переключает symlink `current` и перезапускает сервисы. Миграции БД автоматически не откатываются, поэтому production deploy всегда создает backup перед миграциями.

## Restore backup

Backups лежат в:

```text
/var/www/zaborprofil/shared/backups/
```

Формат database backup:

```text
<release>_database.dump
```

Формат uploads backup:

```text
<release>_uploads.tar.gz
```

Восстановление БД:

```bash
set -a
source /var/www/zaborprofil/shared/.env.local
set +a

pg_restore --clean --if-exists --no-owner --dbname "$DATABASE_URL" /var/www/zaborprofil/shared/backups/<release>_database.dump
```

Восстановление uploads:

```bash
sudo rm -rf /var/www/zaborprofil/shared/public_html/uploads
sudo mkdir -p /var/www/zaborprofil/shared/public_html
sudo tar -xzf /var/www/zaborprofil/shared/backups/<release>_uploads.tar.gz -C /var/www/zaborprofil/shared/public_html
sudo chown -R www-data:www-data /var/www/zaborprofil/shared/public_html/uploads
```

После restore:

```bash
sudo systemctl reload-or-restart php8.5-fpm
sudo systemctl reload nginx
sudo systemctl restart zaborprofil-messenger
tools/deploy/health-check.sh https://zaborprofil.ru/health
```

## systemd worker

Сервис должен называться так же, как `WORKER_SERVICE` в deploy env. По умолчанию это `zaborprofil-messenger`.
Template находится в `tools/deploy/templates/zaborprofil-messenger.service`.
