# Zaborprofil CMS Engine

Новый Symfony CMS Engine для корпоративного сайта [zaborprofil.ru](https://zaborprofil.ru).

Проект заменяет WordPress, но не импортирует WordPress-контент автоматически. Контент переносится вручную через будущую кастомную админ-панель.

## Стек

- PHP `>=8.4`
- Symfony `8.x`
- PostgreSQL `>=18`
- Redis
- Doctrine ORM / DBAL
- Symfony Security, Messenger, Validator, Serializer, Mailer
- Twig
- Tailwind CSS
- Vite
- Vue 3 для админ-панели
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector

Docker намеренно не используется. Проект рассчитан на обычный VPS: Nginx, PHP-FPM, PostgreSQL, Redis, systemd и Git-based deploy.

## Структура

- `public_html/` — web root, здесь лежит `index.php`.
- `src/Shared/` — общие контракты, инфраструктура и UI-адаптеры.
- `src/Module/` — модули модульного монолита.
- `assets/site/` — frontend публичного сайта.
- `assets/admin/` — Vue entrypoint будущей админки.
- `templates/` — Twig-шаблоны.
- `docs/` — документация на русском языке.

## Быстрый старт

```bash
composer install
npm install
npm run build
php bin/console doctrine:migrations:migrate
```

Проверка:

```bash
php bin/console about
php bin/console router:match /health
vendor/bin/phpunit
```

Откройте:

- `/health`
- `/admin/login`

## Следующий этап

После фундамента реализуются базовые модули `Shared`, `User`, `Auth`, `Admin`, а затем `Content/Page/PageBlock`.
