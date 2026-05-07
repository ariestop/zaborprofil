# ADR-0009: Деплой на VPS без Docker

## Статус

Accepted, 2026.

## Контекст

Существуют разные способы деплоя:

- Docker / Compose;
- Kubernetes;
- Native systemd + nginx + php-fpm на VPS;
- PaaS (Platform.sh / Heroku / Vercel-style).

Текущие требования:

- одна команда, ограниченный DevOps-ресурс;
- небольшой production трафик;
- бюджет на VPS;
- нужна предсказуемость и простой rollback;
- backup БД и uploads.

## Решение

Деплой на VPS native стеком:

- **nginx** (>= 1.30.0) — TLS, reverse proxy.
- **php-fpm 8.5+** — Symfony app.
- **PostgreSQL 18** — БД.
- **Redis 8** — cache.
- **systemd** — управление сервисами и Messenger worker.
- **Git-based release deploy** — структура `releases/<ts>` + symlink `current`.
- **GitHub Actions** делает SSH деплой через `tools/deploy/*.sh`.

## Причины

- **Простота.** Меньше слоёв = проще диагностика.
- **Производительность.** Native FPM + Postgres на VPS с разумным размером уверенно держат текущую нагрузку.
- **Низкая стоимость.** Не нужны k8s-кластеры.
- **Fast rollback.** `ln -sfn previous current` — мгновенно.
- **Предсказуемые backup’ы.** `pg_dump` + rsync uploads.
- **Хорошая совместимость с CI.** GitHub Actions через SSH/SCP.

## Структура

```text
/var/www/zaborprofil/
├── releases/
│   ├── 20260501-120000/
│   ├── 20260501-150000/
│   └── 20260502-090000/   <- current
├── shared/
│   ├── .env.local
│   ├── public_html/uploads/
│   ├── var/log/
│   └── backups/
└── current -> releases/20260502-090000
```

## Последствия

- Все environment-specific values — в `shared/.env.local`.
- `current` — atomic symlink switch.
- Старые release’ы накапливаются — нужен периодический cleanup.
- Расхождение с Docker dev есть, но контролируется фиксацией версий.
- Невозможен (легко) horizontal scaling без архитектурных изменений; на текущей нагрузке это и не нужно.

## Workflow

1. CI green.
2. Tag `v*` → GitHub Actions запускает `verify-ci` → `deploy-staging` → `deploy-production`.
3. На VPS: SSH запускает `tools/deploy/deploy-production.sh`.
4. Backup → release dir → composer install → npm build → migration → cache warmup → symlink switch → reload services → healthcheck → готово / rollback.

## Альтернативы

- **Docker prod.** Дополнительный оверхед, без явной выгоды на текущем масштабе.
- **Kubernetes.** Огромная сложность для одной команды.
- **PaaS.** Дороже, vendor lock-in, ограниченный контроль.
- **Capistrano / Deployer.** Можно ввести в будущем, но bash-скриптов достаточно.

## Когда пересмотреть

- Несколько серверов / необходимость auto-scaling.
- Multi-region deploy.
- Появление полноценной DevOps-команды.
- Переход на immutable infrastructure / GitOps.

## Связанные документы

- [34-deployment](../34-deployment.md)
- [35-cicd](../35-cicd.md)
- [37-runbooks](../37-runbooks.md)
- [27-config-and-env](../27-config-and-env.md)
- [adr/0008-docker-for-local-development](0008-docker-for-local-development.md)
