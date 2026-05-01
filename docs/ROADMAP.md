# Roadmap

## Этап 1

- Symfony 8 skeleton.
- PHP 8.4 constraint.
- PostgreSQL, Redis, Doctrine, Security.
- PHPUnit, PHPStan, PHP-CS-Fixer, Rector.
- Vite, Tailwind CSS, Vue 3 entrypoint.
- `src/Shared` и `src/Module`.
- `/health` и `/admin/login`.
- GitHub Actions CI.
- Базовая документация.

## Этап 2

Частично готово: есть базовые `User`, `Auth`, `Admin`.

Дальше:

- seed-команда администратора;
- UserChecker для неактивных администраторов;
- расширенная матрица ролей и прав;
- audit hooks для действий в админке.

## Этап 3

Готово: реализованы `Content`, `Page`, `PageBlock`, enum, Doctrine repositories, application handlers и Admin API.

## Этап 4

Готово базово: опубликованные страницы открываются по `Page.path`, блоки рендерятся через Twig partials.

Дальше:

- расширить набор Twig partials для всех типов блоков;
- добавить preview mode;
- добавить полноценный Vue block editor.

## Этапы 5-13

Следующие приоритеты: SEO metadata, sitemap, robots, redirects, media, lead forms, portfolio, settings, menu, audit log, Vue admin UI и deploy-документация.
