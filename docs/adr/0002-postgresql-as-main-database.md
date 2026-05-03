# ADR-0002: PostgreSQL как основная БД

## Статус

Accepted, 2026.

## Контекст

CMS-движок требует:

- надёжное хранение контента, заявок, пользователей, заказов в будущем;
- JSON-поля для гибких структур (PageBlock content/settings, Setting value);
- partial unique indexes (живые URL без soft-deleted);
- сильная транзакционная семантика;
- зрелые backup / replication / PITR.

Текущая практика PHP-сообщества часто использует MySQL. Для DDD-проектов с нагрузкой на сложные структуры PostgreSQL даёт более качественный фундамент.

## Решение

**PostgreSQL ≥ 18** как основная и единственная база данных проекта.

## Причины

- **JSONB.** Используется для `content`/`settings` блоков и `Setting.value`. MySQL JSON хуже по индексации.
- **Partial indexes.** `uniq_content_pages_path_active WHERE deleted_at IS NULL` — критично для soft-delete + уникальности.
- **CTE / window functions / FTS.** Понадобятся в каталоге, отчётах, SEO-аудите.
- **Транзакции.** Полная поддержка serializable, advisory locks.
- **PITR / WAL.** Лучше встроенная поддержка, чем у MySQL.
- **Doctrine 3.** Полная поддержка PostgreSQL 18, специфические типы.
- **Production-ready.** В Hetzner / любом VPS легко поставить из официальных пакетов.

## Последствия

- В проекте используются Postgres-specific фичи (jsonb, partial unique). Кроссбазовость — не цель.
- Тесты гоняются на Postgres в CI; локально допустим SQLite, но критичные тесты должны быть на Postgres.
- Doctrine `server_version: '18'` зафиксировано в `doctrine.yaml`.
- Backup-стратегия — `pg_dump --format=custom` + WAL (целевое для PITR).
- В тестах jsonb может вести себя иначе, чем SQLite — это учитывается (см. `CONTENT_ENGINE.md`).

## Альтернативы

- **MySQL/MariaDB** — слабее по JSONB и partial indexes.
- **SQLite** — только для тестов, не для production.
- **MongoDB / DynamoDB** — не реляционная модель, не подходит для CMS с сложной структурой.

## Когда пересмотреть

- Нужно перейти на managed-сервис, который не поддерживает PG 18+.
- Критичная фича доступна только в другой БД.
- Проблема производительности, которую PG не закрывает (маловероятно).
