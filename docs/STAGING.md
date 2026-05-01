# Staging

Staging разворачивается на VPS без Docker и должен быть максимально похож на production.

## Stack

- Nginx.
- PHP-FPM 8.4.
- PostgreSQL 18 или ближайшая стабильная версия.
- Redis с паролем.
- systemd worker для Symfony Messenger.
- SSL для `staging.zaborprofil.ru`.
- Отдельная staging database.
- Отдельный uploads directory.

## Структура на сервере

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

## Shared env

На сервере создайте:

```bash
mkdir -p /var/www/zaborprofil/shared/public_html/uploads
mkdir -p /var/www/zaborprofil/shared/var/log
cp tools/deploy/shared-env-example.sh /var/www/zaborprofil/shared/.env.local
```

Заполните staging значения:

```dotenv
APP_ENV=staging
APP_DEBUG=0
APP_SECRET=unique-staging-secret
DATABASE_URL="postgresql://zaborprofil_staging:password@127.0.0.1:5432/zaborprofil_staging?serverVersion=18&charset=utf8"
REDIS_URL="redis://:password@127.0.0.1:6379/1"
SITE_URL="https://staging.zaborprofil.ru"
DEFAULT_URI="https://staging.zaborprofil.ru"
```

Staging и production обязаны использовать разные `APP_SECRET`, базы данных и Redis DB/password.

## Nginx

Document root:

```text
/var/www/zaborprofil/current/public_html
```

Неизвестные URL должны идти в `index.php`. Для закрытого staging включите basic auth или IP allowlist.

Templates:

- `tools/deploy/templates/nginx-staging.conf`
- `tools/deploy/templates/zaborprofil-messenger-staging.service`

Установка:

```bash
sudo cp tools/deploy/templates/nginx-staging.conf /etc/nginx/sites-available/zaborprofil-staging.conf
sudo ln -sfn /etc/nginx/sites-available/zaborprofil-staging.conf /etc/nginx/sites-enabled/zaborprofil-staging.conf
sudo nginx -t
sudo systemctl reload nginx

sudo cp tools/deploy/templates/zaborprofil-messenger-staging.service /etc/systemd/system/zaborprofil-messenger-staging.service
sudo systemctl daemon-reload
sudo systemctl enable --now zaborprofil-messenger-staging.service
```

## Deploy

Ручной запуск на VPS:

```bash
BRANCH=staging \
APP_ROOT=/var/www/zaborprofil \
HEALTH_URL=https://staging.zaborprofil.ru/health \
tools/deploy/deploy-staging.sh
```

Через GitHub Actions: push в `develop` или `staging` запускает deploy staging при наличии SSH secrets.

## Health-check

```bash
tools/deploy/health-check.sh https://staging.zaborprofil.ru/health
```

Ожидаемый ответ содержит:

```json
{"status":"ok","app":"ok","database":"ok","redis":"ok","storage":"ok"}
```
