# Деплой на VPS

Проект разворачивается без Docker.

## Базовая схема

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
9. Перезапустить PHP-FPM и messenger worker.

## Nginx

Document root должен указывать на:

```text
/var/www/zaborprofil/current/public_html
```

Все неизвестные URL должны проксироваться в `public_html/index.php`.

## Откат

Откат выполняется переключением `current` на предыдущий release и перезапуском PHP-FPM.
