# Переменные deploy scripts

Deploy scripts читают переменные окружения. Значения можно передавать перед командой или задать в shell профиле deploy-пользователя.

## Общие

- `APP_ROOT` — корень release layout, по умолчанию `/var/www/zaborprofil`.
- `REPOSITORY` — Git repository, по умолчанию `git@github.com:ariestop/zaborprofil.git`.
- `BRANCH` — ветка или tag для deploy.
- `HEALTH_URL` — URL health-check.
- `KEEP_RELEASES` — сколько старых релизов хранить, по умолчанию `5`.
- `PHP_BIN` — путь к PHP, по умолчанию `php`.
- `COMPOSER_BIN` — путь к Composer, по умолчанию `composer`.
- `NPM_BIN` — путь к npm, по умолчанию `npm`.
- `PHP_FPM_SERVICE` — systemd unit PHP-FPM, по умолчанию `php8.5-fpm`.
- `NGINX_SERVICE` — systemd unit Nginx, по умолчанию `nginx`.
- `WORKER_SERVICE` — systemd unit Messenger worker, по умолчанию `zaborprofil-messenger`.

## Production

- `CONFIRM_STAGING_DEPLOYED=yes` — обязательное подтверждение успешного staging deploy.
- `DATABASE_BACKUP_COMMAND` — опциональная кастомная команда backup database. Если не задана, используется `pg_dump` на основе `DATABASE_URL`.

## Health-check

- `HEALTH_RETRIES` — количество попыток, по умолчанию `10`.
- `HEALTH_SLEEP_SECONDS` — пауза между попытками, по умолчанию `3`.
