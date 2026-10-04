# 36. Backup и restore

## Стратегия

| Объект | Способ | Частота | Retention | Где |
|---|---|---|---|---|
| MySQL (full) | `mysqldump --single-transaction` + `gzip -9` | ежедневно | 30 дней | `/var/www/zaborprofil/shared/backups/db/` |
| MySQL (per-deploy) | `mysqldump` перед миграцией | per deploy | `BACKUP_RETENTION_DAYS` (по умолчанию 14 дней) | `shared/backups/db/` |
| `public_html/uploads/` | `rsync` или `tar` | ежедневно / per deploy | `BACKUP_RETENTION_DAYS` для per-deploy архивов | `shared/backups/uploads/` |
| `.env.local` (зашифрованный) | копия в безопасное хранилище | при изменении | бессрочно | offline / vault |

> Redis в проекте не используется. Кэш приложения хранится в файлах (`var/cache/<env>/pools/`) и **не бэкапится**: он полностью пересобирается из БД. Очередь сообщений (Doctrine transport, таблица `messenger_messages`) и failed-сообщения находятся в MySQL и входят в обычный дамп БД. Сессии (`var/sessions/<env>/`) тоже не бэкапятся: потеря означает только повторный вход администраторов.

Git не является хранилищем backup'ов. В Git можно хранить только воспроизводимое dev-состояние: migrations, fixtures и при необходимости маленький обезличенный dev snapshot/seed. Production/staging dumps, per-deploy backups, Docker volumes, uploads с реальными файлами, секреты и персональные данные в Git не коммитятся. Правила для dev-состояния описаны в [47-dev-database-state](47-dev-database-state.md).

## MySQL backup

```bash
mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    -h 127.0.0.1 \
    -u zaborprofil -p \
    zaborprofil \
    | gzip -9 > /var/www/zaborprofil/shared/backups/db/zaborprofil-$(date +%Y%m%d-%H%M%S).sql.gz

# Проверка архива
gzip -t /var/www/zaborprofil/shared/backups/db/zaborprofil-20260502-090000.sql.gz
```

`--single-transaction` даёт консистентный снимок InnoDB без блокировки таблиц. Пароль не следует передавать в argv: использовать `~/.my.cnf` или `--defaults-extra-file` с правами `600`.

Внутри `tools/deploy/deploy-production.sh` это уже выполняется перед `migrations:migrate`: `tools/deploy/common.sh` строит временный defaults-extra-file из `DATABASE_URL` (пароль не попадает в argv) и вызывает `mysqldump_cli`. Per-deploy dump сохраняется в `shared/backups/db/<release>_database.sql.gz` и проверяется через `gzip -t`.

> DDL в MySQL не транзакционен (неявный commit), поэтому неудачная миграция может примениться частично и откатить её нельзя. Backup перед боевой миграцией обязателен.

## MySQL restore

```bash
# Создать пустую БД (если нужно)
mysql -h 127.0.0.1 -u zaborprofil -p -e \
    "CREATE DATABASE zaborprofil_restored CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"

# Восстановить
gzip -dc /var/www/zaborprofil/shared/backups/db/zaborprofil-20260502-090000.sql.gz \
    | mysql -h 127.0.0.1 -u zaborprofil -p --default-character-set=utf8mb4 zaborprofil_restored
```

Затем — переключение приложения:

1. Остановить worker: `systemctl stop zaborprofil-messenger`.
2. Изменить `DATABASE_URL` в `shared/.env.local` на восстановленную БД (в MySQL нет `RENAME DATABASE`, поэтому БД «переименовывается» только повторной заливкой дампа под нужным именем).
3. `systemctl reload php8.5-fpm`.
4. Очистить файловый кэш приложения: `php bin/console cache:pool:clear cache.app` (кэш мог содержать данные до восстановления).
5. Smoke test, затем worker on.

## Uploads backup

```bash
rsync -av --delete \
    /var/www/zaborprofil/shared/public_html/uploads/ \
    /var/www/zaborprofil/shared/backups/uploads/$(date +%Y%m%d)/
```

Целевое: rsync в off-site (S3-compatible, encrypted at rest).

В production deploy uploads дополнительно архивируются в `shared/backups/uploads/<release>_uploads.tar.gz`; архив сразу проверяется через `tar -tzf`.

## Uploads restore

```bash
rsync -av \
    /var/www/zaborprofil/shared/backups/uploads/20260501/ \
    /var/www/zaborprofil/shared/public_html/uploads/
```

## Encryption

Любой бэкап вне VPS — должен быть зашифрован:

- `gpg --symmetric --cipher-algo AES256 backup.sql.gz` или
- AWS S3 SSE / hetzner object storage SSE-C.

Ключ — отдельно от backups, в менеджере паролей команды.

## Verification

Бэкап без проверки = нет бэкапа. Минимум:

- ежедневный smoke restore на отдельный сервер / docker;
- проверка `gzip -t backup.sql.gz` и просмотр начала дампа (`gzip -dc backup.sql.gz | head`);
- восстановление во временную БД и проверка количества записей (так делает `tools/deploy/restore-rehearsal.sh`).

## Disaster recovery

Сценарии:

| Случай | Действия |
|---|---|
| Удалена строка/таблица | Залить последний dump в `_restored` БД, перенести нужные данные в основную (`INSERT ... SELECT` между БД либо `mysqldump <db> <table>` из восстановленной) |
| Полный crash БД | Поднять MySQL 8.4 на резервной машине, восстановить latest dump, переключить `DATABASE_URL` |
| Crash uploads | rsync из shared/backups/ |
| Crash сервера | Подготовленный playbook (Ansible — целевое); восстановление из off-site backup |
| Утечка секретов | rotate `APP_SECRET`, DB password, Telegram token; logout всех админов |

## Restore drill

Каждые 3 месяца:

- Полный restore на staging из последнего production dump;
- Проверка количества Page/PageBlock, MediaAsset, MenuItem и Lead;
- Проверка sitemap, login, preview links, Media Library и публичной lead-формы;
- Запуск `php bin/console app:smoke:test` после переключения на восстановленную БД.

Для технической проверки backup-файлов без переключения приложения используйте:

```bash
CONFIRM_RESTORE_REHEARSAL=yes \
APP_ROOT=/var/www/zaborprofil \
tools/deploy/restore-rehearsal.sh \
    --db-backup /var/www/zaborprofil/shared/backups/db/<backup>.sql.gz \
    --uploads-backup /var/www/zaborprofil/shared/backups/uploads/<backup>.tar.gz
```

Скрипт проверяет архив через `gzip -t`, восстанавливает dump во временную БД `<db>_restore_rehearsal_<timestamp>`, проверяет наличие public tables,
распаковывает uploads archive во временную директорию и удаляет временные данные
после успешного rehearsal.

## Что нельзя

- Хранить backup в той же папке, что и release (его удалят при `releases/cleanup`).
- Хранить backup на той же VM без off-site копии.
- Не шифровать backup, отправляемый наружу.
- Backup в `public_html/` (riski public exposure).
- Коммитить реальные backup-файлы БД или uploads в Git; Git допускается только для обезличенных dev seeds/snapshots.

## Чек-лист настройки backup

- [ ] cron / systemd timer для ежедневного `mysqldump`.
- [ ] cron / systemd timer для ежедневного rsync uploads.
- [ ] Off-site копирование (S3 / Hetzner / B2).
- [ ] Encryption.
- [ ] Retention настроен.
- [ ] `BACKUP_RETENTION_DAYS` соответствует production policy.
- [ ] Restore drill план в календаре.
- [ ] Логирование backup’ов в канал `deploy`.
- [ ] Alert при отсутствии успешного backup за 36ч.

## Связанные документы

- [34-deployment](34-deployment.md)
- [37-runbooks](37-runbooks.md)
- [17-doctrine-and-database](17-doctrine-and-database.md)
- [18-migrations](18-migrations.md)
- [25-files-and-uploads](25-files-and-uploads.md)
