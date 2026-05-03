# 36. Backup и restore

## Стратегия

| Объект | Способ | Частота | Retention | Где |
|---|---|---|---|---|
| PostgreSQL (full) | `pg_dump --format=custom` | ежедневно | 30 дней | `/var/www/zaborprofil/shared/backups/db/` |
| PostgreSQL (per-deploy) | `pg_dump` перед миграцией | per deploy | `BACKUP_RETENTION_DAYS` (по умолчанию 14 дней) | `shared/backups/db/` |
| `public_html/uploads/` | `rsync` или `tar` | ежедневно / per deploy | `BACKUP_RETENTION_DAYS` для per-deploy архивов | `shared/backups/uploads/` |
| `.env.local` (зашифрованный) | копия в безопасное хранилище | при изменении | бессрочно | offline / vault |
| Redis | RDB снапшот | (опционально) ежечасно | 7 дней | `shared/backups/redis/` |

> Redis в этом проекте — **cache + очередь сообщений**. Терять данные можно с минимальным ущербом (cache rebuild + retry messages из failed). Backup не критичен; целевое — RDB на всякий случай.

Git не является хранилищем backup'ов. В Git можно хранить только воспроизводимое dev-состояние: migrations, fixtures и при необходимости маленький обезличенный dev snapshot/seed. Production/staging dumps, per-deploy backups, Docker volumes, uploads с реальными файлами, секреты и персональные данные в Git не коммитятся. Правила для dev-состояния описаны в [47-dev-database-state](47-dev-database-state.md).

## PostgreSQL backup

```bash
PGPASSWORD=... pg_dump \
    -h 127.0.0.1 \
    -U zaborprofil \
    -d zaborprofil \
    --format=custom \
    --compress=9 \
    --file=/var/www/zaborprofil/shared/backups/db/zaborprofil-$(date +%Y%m%d-%H%M%S).dump
```

Внутри `tools/deploy/deploy-production.sh` это уже выполняется перед `migrations:migrate`. Per-deploy dump сохраняется в `shared/backups/db/<release>_database.dump` и проверяется через `pg_restore -l`.

## PostgreSQL restore

```bash
# Создать пустую БД (если нужно)
createdb -h 127.0.0.1 -U zaborprofil zaborprofil_restored

# Восстановить
pg_restore -h 127.0.0.1 -U zaborprofil \
    --dbname=zaborprofil_restored \
    --no-owner --no-privileges \
    /var/www/zaborprofil/shared/backups/db/zaborprofil-20260502-090000.dump
```

Затем — переключение приложения:

1. Остановить worker: `systemctl stop zaborprofil-messenger`.
2. Изменить `DATABASE_URL` в `shared/.env.local` на восстановленную БД (или переименовать БД).
3. `systemctl reload php8.5-fpm`.
4. Smoke test, затем worker on.

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

- `gpg --symmetric --cipher-algo AES256 backup.dump` или
- AWS S3 SSE / hetzner object storage SSE-C.

Ключ — отдельно от backups, в менеджере паролей команды.

## Verification

Бэкап без проверки = нет бэкапа. Минимум:

- ежедневный smoke restore на отдельный сервер / docker;
- проверка `pg_restore -l backup.dump` (читает headers).

## Disaster recovery

Сценарии:

| Случай | Действия |
|---|---|
| Удалена строка/таблица | `pg_restore` нужной таблицы из последнего dump в `_restored` БД, копия данных в основную |
| Полный crash БД | Поднять Postgres на резервной машине, восстановить latest dump, переключить `DATABASE_URL` |
| Crash uploads | rsync из shared/backups/ |
| Crash сервера | Подготовленный playbook (Ansible — целевое); восстановление из off-site backup |
| Утечка секретов | rotate `APP_SECRET`, DB password, Redis password, Telegram token; logout всех админов |

## Restore drill

Каждые 3 месяца:

- Полный restore на staging из последнего production dump;
- Проверка количества Page/PageBlock, MediaAsset, MenuItem и Lead;
- Проверка sitemap, login, preview links, Media Library и публичной lead-формы;
- Запуск `php bin/console app:smoke:test` после переключения на восстановленную БД.

## Что нельзя

- Хранить backup в той же папке, что и release (его удалят при `releases/cleanup`).
- Хранить backup на той же VM без off-site копии.
- Не шифровать backup, отправляемый наружу.
- Backup в `public_html/` (riski public exposure).
- Коммитить реальные backup-файлы БД или uploads в Git; Git допускается только для обезличенных dev seeds/snapshots.

## Чек-лист настройки backup

- [ ] cron / systemd timer для ежедневного `pg_dump`.
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
