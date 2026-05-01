# Тестирование

## Команды

```bash
composer check:syntax
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
vendor/bin/phpunit
npm run build
```

## Структура

- `tests/Unit` — unit-тесты.
- `tests/Integration` — интеграционные тесты.
- `tests/Functional` — функциональные тесты Symfony.
- `tests/E2E` — будущие e2e-сценарии.

На первом этапе добавлены smoke-тесты для Kernel, `/health` и `/admin/login`.

Для Content Engine добавлены:

- Unit-тесты доменных инвариантов `Page` и `PageBlock`;
- Integration-тесты Doctrine repositories;
- Functional-тесты Admin API и публичного рендера опубликованной страницы.

В `test` окружении Doctrine использует SQLite-файл в cache-каталоге, чтобы локальные тесты не зависели от доступности PostgreSQL. Production и dev окружения остаются на PostgreSQL.
