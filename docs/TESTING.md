# Тестирование

## Команды

```bash
composer check:syntax
composer validate --strict
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
php bin/console doctrine:schema:validate --env=test --skip-sync
php bin/console lint:container --env=test
php bin/console lint:twig templates --env=test
php bin/console app:smoke:test --env=test
vendor/bin/phpunit
npm run build
```

## Структура

- `tests/Unit` — unit-тесты.
- `tests/Integration` — интеграционные тесты.
- `tests/Functional` — функциональные тесты Symfony.
- `tests/E2E` — будущие e2e-сценарии.

`app:smoke:test` проверяет release-readiness: ключевые routes, обязательные env, Vite manifest и writable uploads storage.

Для Content Engine добавлены:

- Unit-тесты доменных инвариантов `Page` и `PageBlock`;
- Integration-тесты Doctrine repositories;
- Functional-тесты Admin API и публичного рендера опубликованной страницы.

В `test` окружении Doctrine использует SQLite-файл в cache-каталоге, чтобы локальные тесты не зависели от доступности PostgreSQL. Production и dev окружения остаются на PostgreSQL.

## Content Engine

Проверяемые сценарии:

- страница создается в статусе `draft`;
- некорректный `slug` отклоняется доменной моделью;
- опубликованная страница загружается по `path`;
- блоки сохраняют `content` и `settings`;
- отключенные блоки не попадают в публичный рендер;
- черновик публично возвращает `404`;
- Admin API создает страницу, добавляет блок и публикует страницу.

Перед запуском тестов можно очистить test cache:

```bash
php bin/console cache:clear --env=test
```
