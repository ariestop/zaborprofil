# Тестирование

## Команды

```bash
make up
make test-db
make quality
```

PHPUnit/Doctrine проверки локально запускаются только через Docker Compose и
PostgreSQL service `postgres`. SQLite для тестов запрещён.

## Структура

- `tests/Unit` — unit-тесты.
- `tests/Integration` — интеграционные тесты.
- `tests/Functional` — функциональные тесты Symfony.
- `tests/E2E` — e2e-сценарии для ручного/будущего автопрогона (например, `AdminContentEditorCaretScenario.md`).

`app:smoke:test` проверяет release-readiness: ключевые routes, обязательные env, Vite manifest и writable uploads storage.

Для Content Engine добавлены:

- Unit-тесты доменных инвариантов `Page` и `PageBlock`;
- Integration-тесты Doctrine repositories;
- Functional-тесты Admin API и публичного рендера опубликованной страницы.

В `test` окружении Doctrine использует отдельную PostgreSQL БД
`zaborprofil_test` из Docker Compose. Production, dev и test окружения должны
проверяться на одном семействе СУБД; SQLite не используется.

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
make test-db
docker compose exec -T --user www-data app php bin/console cache:clear --env=test
```
