# ADR-0002: MySQL как основная БД

## Статус

Accepted, 2026. Обновлён: изначальное решение (PostgreSQL ≥ 18) заменено по запросу заказчика — staging размещается на хостинге Beget, где доступен MySQL, но нет PostgreSQL 18 (и Redis). Сопутствующее решение по кэшу — [ADR-0007](0007-filesystem-cache-and-doctrine-messenger.md).

## Контекст

CMS-движок требует:

- надёжное хранение контента, заявок, пользователей, заказов в будущем;
- JSON-поля для гибких структур (PageBlock content/settings, Setting value);
- уникальность живых URL без soft-deleted записей;
- сильная транзакционная семантика;
- зрелые backup / replication.

Изначально выбирался PostgreSQL: лучшие JSONB, partial indexes, транзакционный DDL. Однако staging-окружение запускается на хостинге Beget, где доступна только управляемая MySQL (8.x); PostgreSQL 18 там недоступен, а держать отдельную БД вне хостинга ради этого проекта нецелесообразно. Нужно, чтобы dev, CI, staging и production работали на одной и той же СУБД.

## Решение

**MySQL ≥ 8.4 (InnoDB, `utf8mb4`, collation `utf8mb4_0900_ai_ci`)** как основная и единственная база данных проекта.

- Doctrine DBAL: драйвер `pdo_mysql`, `server_version: '8.4'`, `charset: utf8mb4`; `DATABASE_URL=mysql://user:pass@host:3306/db?serverVersion=8.4&charset=utf8mb4`.
- Локально и в CI — Docker-образ `mysql:8.4`; staging и production — нативный MySQL (на Beget — управляемый хостингом).

## Причины

- **Доступность на целевом хостинге.** MySQL есть на Beget; PostgreSQL 18 и Redis — нет.
- **JSON.** Нативный тип `JSON` (бинарный формат, функции `JSON_EXTRACT`, `->>`, `JSON_CONTAINS`, индексы по generated column / functional / multi-valued) покрывает потребности `content`/`settings` и `Setting.value`.
- **Зрелость и распространённость.** Огромная экосистема PHP/Symfony, привычные инструменты (`mysqldump`, Adminer, `performance_schema`).
- **Транзакции.** InnoDB: ACID, row-level locking, MVCC, FK; изоляция по умолчанию `REPEATABLE READ`.
- **Online DDL.** `ALGORITHM=INSTANT/INPLACE, LOCK=NONE` позволяют добавлять колонки и индексы без блокировки записи (см. [18-migrations](../18-migrations.md) §5).
- **Doctrine DBAL 4.** Полная поддержка MySQL 8.x; ULID хранится как `BINARY(16)`.

## Последствия

- В проекте не используются PostgreSQL-специфичные возможности: `jsonb`-операторы (`@>`, `?`), GIN/GiST, partial indexes, `ILIKE`, `tsvector`/FTS, `ON CONFLICT`, sequences, `RETURNING`, `CREATE INDEX CONCURRENTLY`. Кроссбазовость по-прежнему не цель: проект использует (и допускает использовать) MySQL-специфичные возможности — тип `JSON`, generated columns, `INSERT ... ON DUPLICATE KEY UPDATE`, online DDL.
- **Нет partial unique indexes.** Уникальность `content_pages.path` среди живых записей обеспечивается `PageRepositoryInterface::existsByPath()`; на уровне БД возможна замена unique-индексом по generated column `IF(deleted_at IS NULL, path, NULL)` (см. [17-doctrine-and-database](../17-doctrine-and-database.md) §13).
- **DDL не транзакционен.** Любой `CREATE/ALTER/DROP` неявно коммитит транзакцию, поэтому неудачная миграция может примениться частично. Миграции — маленькие, `isTransactional(): false`, перед боевой миграцией — обязательный `mysqldump` ([18-migrations](../18-migrations.md) §7, §9).
- **Collation `_ci` / `_ai`.** Сравнение строк и unique-индексы регистро- и акцент-нечувствительны; для чувствительных значений явно задаётся `utf8mb4_bin`.
- **ULID** — `BINARY(16)` (нет нативного GUID-типа); в SQL-консоли id читается через `HEX(id)` ([ADR-0011](0011-ulid-identifiers.md)).
- **Поиск.** Вместо PostgreSQL FTS — `LIKE`, а в будущем InnoDB `FULLTEXT` с `ngram`-парсером (для русского языка) либо внешний поисковый движок (Meilisearch/Typesense).
- **Upsert** — `INSERT ... ON DUPLICATE KEY UPDATE`; case-insensitive сравнение — по умолчанию (collation `_ci`).
- Тесты гоняются на MySQL в CI и локально через Docker Compose (БД `zaborprofil_test`); SQLite для тестов запрещён.
- Doctrine `server_version: '8.4'` зафиксировано в `doctrine.yaml`.
- Backup-стратегия — `mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 | gzip -9` (`<release>_database.sql.gz`, проверка `gzip -t`); восстановление — `gzip -dc file.sql.gz | mysql <db>`. PITR через binary log — целевое (на управляемом хостинге зависит от возможностей провайдера).
- Тесты должны видеть реальное поведение MySQL (JSON, collation, ограничения) без SQLite-расхождений.

## Альтернативы

- **PostgreSQL ≥ 18** (исходное решение) — рассмотрен и отвергнут из-за ограничений хостинга Beget (нет PostgreSQL 18). Остаётся технически сильнее по JSONB, partial indexes, FTS, транзакционному DDL и PITR/WAL; к нему возможен возврат при переезде на собственный VPS/managed PostgreSQL.
- **MariaDB** — совместима по протоколу, но отличается в JSON (хранится как `LONGTEXT`), generated columns и online DDL; целевой платформой не является.
- **SQLite** — не используется: кроссбазовость не является целью проекта.
- **MongoDB / DynamoDB** — не реляционная модель, не подходит для CMS с сложной структурой.

## Когда пересмотреть

- Staging/production переезжает на инфраструктуру, где доступен PostgreSQL ≥ 18, и преимущества JSONB, partial indexes, FTS и транзакционного DDL становятся критичными.
- Нужны возможности, которых MySQL не даёт (сложный FTS на русском с морфологией, геоданные уровня PostGIS).
- Хостинг прекращает поддержку MySQL 8.4 или меняет её ограничения (права, `sql_mode`, версии).
