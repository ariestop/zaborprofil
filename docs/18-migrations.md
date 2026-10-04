# 18. Миграции

> **Назначение документа.** Зафиксировать правила работы с Doctrine Migrations в проекте `zaborprofil`. Любая правка схемы БД — через миграции; любая миграция — через checklists этого документа.
>
> **Аудитория.** Backend-разработчики, AI-агенты, DevOps, тимлиды.
>
> **Связанные документы.** [17-doctrine-and-database](17-doctrine-and-database.md), [05-domain-model](05-domain-model.md), [34-deployment](34-deployment.md), [36-backup-restore](36-backup-restore.md), [37-runbooks](37-runbooks.md) (инциденты 14–15).

---

## 1. Инструмент и конфигурация

- Bundle: `doctrine/doctrine-migrations-bundle` v4 (см. [composer.json](../composer.json)).
- Конфигурация: `config/packages/doctrine_migrations.yaml`.
- Папка миграций: `migrations/`, namespace `DoctrineMigrations`.
- Хранение состояния: таблица `doctrine_migration_versions` в основной БД.
- СУБД: MySQL 8.4 (InnoDB, `utf8mb4` / `utf8mb4_0900_ai_ci`). Весь SQL в миграциях — диалекта MySQL.

> **Фактическое состояние.** История миграций пересоздана при переходе на MySQL: вместо цепочки PostgreSQL-миграций в проекте одна начальная `Version20261004000100` — полная схема (`CREATE TABLE ... ENGINE = InnoDB`, FK, индексы) плюс seed системных шаблонов страниц (`content_page_templates`). Новые миграции добавляются поверх неё. Doctrine Migrations 4 и DBAL 4.

---

## 2. Имена и структура файла

- `Version<YYYYMMDDHHMMSS>.php` — монотонно возрастают.
- Класс `final`, `extends AbstractMigration`.
- Один файл = одна логически целая правка схемы или данных.
- Если в одной задаче нужно два независимых изменения (например, новая таблица + индекс на существующую) — два разных файла.

Минимальный шаблон:

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable summary column to content_pages (online, INSTANT)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages ADD COLUMN summary LONGTEXT DEFAULT NULL, ALGORITHM=INSTANT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_pages DROP COLUMN summary, ALGORITHM=INSTANT');
    }

    public function isTransactional(): bool
    {
        return false; // DDL в MySQL коммитится неявно, см. §7.1
    }
}
```

> Типы в SQL — MySQL: Doctrine `text` → `LONGTEXT`, `boolean` → `TINYINT`, `datetime_immutable` → `DATETIME`, `json` → `JSON`, `ulid` → `BINARY(16)` (см. [17-doctrine-and-database](17-doctrine-and-database.md) §2.1).

> Метод `getDescription()` обязателен: это описание выводится в админке `Настройки → Миграции` и должно подробно объяснять, что делает миграция и зачем она нужна.

---

## 3. Создание миграции

```bash
php bin/console doctrine:migrations:diff
```

Doctrine генерирует diff между текущей схемой и attribute-маппингом. Сгенерированный файл нужно **вручную просмотреть**:

- удалить лишние команды, которые Doctrine сгенерировал «на всякий случай»;
- добавить data migration, если требуется backfill;
- добавить то, чего attributes не выражают: generated columns, functional/multi-valued/FULLTEXT-индексы, `CHECK`-constraints, triggers; для «partial unique» — unique-индекс по generated column (см. [17-doctrine-and-database](17-doctrine-and-database.md) §13);
- вручную дописать `ALGORITHM=INSTANT|INPLACE` и `LOCK=NONE` к `ALTER TABLE` на существующих таблицах (Doctrine их не генерирует), а при необходимости — `SET SESSION lock_wait_timeout` (см. §5.0);
- убедиться, что diff не содержит «шума» из-за различий charset/collation (`utf8mb4` / `utf8mb4_0900_ai_ci` заданы в `default_table_options`) и не предлагает удалить вручную созданные generated columns / functional-индексы;
- разбить миграцию на файлы по 1–2 DDL-оператора — DDL в MySQL не откатывается (§7.1);
- зафиксировать `getDescription()`;
- добавить `isTransactional(): false` для любой миграции с DDL (в MySQL DDL неявно коммитит транзакцию).

> **Запрещено.** Коммитить миграцию без чтения её содержимого. Особенно опасно — `DROP COLUMN`, `DROP TABLE`, `MODIFY`/`CHANGE` (смена типа, сужение), переименования: Doctrine может неверно интерпретировать их как destructive.

---

## 4. Применение

Локально (Docker):

```bash
make migrate
# или
php bin/console doctrine:migrations:migrate --no-interaction
```

В CI:

```bash
php bin/console doctrine:migrations:migrate --env=test --allow-no-migration --no-interaction
```

На VPS:

```bash
# Внутри tools/deploy/deploy-*.sh, после composer install,
# до cache:warmup и до переключения current.
php bin/console doctrine:migrations:migrate --no-interaction
```

> **Запрещено.** Запускать `doctrine:schema:update --force` где угодно (dev, staging, prod). Только миграции.

---

## 5. Стратегия zero-downtime: expand / contract

Production деплоится через release switch (см. [34-deployment](34-deployment.md)). В момент `current → новый release` есть короткий период, когда **старый код может видеть новую схему**, а новый код может видеть **старые данные**. Поэтому миграции должны быть совместимы вперёд и назад.

### 5.0 Особенности MySQL: online DDL и metadata locks

MySQL не поддерживает транзакционный DDL, но InnoDB умеет выполнять многие `ALTER TABLE` online. Правила для каждой DDL-миграции:

1. **Всегда указывать `ALGORITHM` и `LOCK` явно.** Если операция не может быть выполнена с заданными параметрами, MySQL вернёт ошибку *до* начала работы, вместо того чтобы молча скопировать таблицу под блокировкой записи:

   ```sql
   ALTER TABLE content_pages ADD COLUMN summary LONGTEXT DEFAULT NULL, ALGORITHM=INSTANT;
   ALTER TABLE content_pages ADD INDEX idx_content_pages_published_at (published_at), ALGORITHM=INPLACE, LOCK=NONE;
   ```

2. Алгоритмы (от лучшего к худшему): `INSTANT` (только метаданные, мгновенно) → `INPLACE` (без копирования таблицы или с rebuild на месте, DML разрешён при `LOCK=NONE`) → `COPY` (создаётся копия таблицы, запись блокируется). `COPY` на больших таблицах на production запрещён.

3. **Metadata lock (MDL).** Даже online DDL в начале и в конце берёт короткий эксклюзивный MDL на таблицу. Если к таблице есть долгая открытая транзакция или медленный запрос, `ALTER` встаёт в очередь на MDL, а **все** новые запросы к этой таблице встают в очередь за ним — сайт «замирает». Поэтому перед DDL:

   ```sql
   SET SESSION lock_wait_timeout = 5;   -- ждать MDL не больше 5 секунд (по умолчанию — 1 год)
   ```

   и проверить, нет ли долгих транзакций: `SELECT trx_id, trx_started, trx_mysql_thread_id FROM information_schema.INNODB_TRX ORDER BY trx_started;`. Если `ALTER` повис, его можно найти в `SHOW FULL PROCESSLIST` (состояние `Waiting for table metadata lock`) или в `performance_schema.metadata_locks`.

4. Во время online DDL изменения от параллельных DML накапливаются в логе (`innodb_online_alter_log_max_size`, по умолчанию 128 MB); при переполнении `ALTER` завершится ошибкой и откатится — для интенсивно пишущих таблиц выполнять DDL вне пика нагрузки.

5. **Нет транзакционного отката.** Каждый DDL-оператор фиксируется сразу. Если миграция из нескольких операторов упала на середине, предыдущие операторы уже применены, а запись в `doctrine_migration_versions` не создана. Поэтому DDL-миграции должны быть **маленькими (один–два оператора)** и желательно идемпотентными (см. §7).

6. Для очень больших таблиц, где нужна копирующая операция (смена типа колонки), использовать внешние утилиты online schema change (`gh-ost`, `pt-online-schema-change`) вне Doctrine Migrations — по отдельному плану, согласованному с архитектором.

Сводка по типовым операциям (MySQL 8.4, InnoDB):

| Операция | Алгоритм | Параллельный DML | Rebuild таблицы |
|---|---|---|---|
| `ADD COLUMN` (nullable или с `DEFAULT`, в любой позиции) | `INSTANT` | да | нет |
| `DROP COLUMN` | `INSTANT` | да | нет (физически строки очищаются при следующем rebuild) |
| `RENAME COLUMN` | `INSTANT` / `INPLACE` | да | нет |
| `ALTER COLUMN ... SET DEFAULT` / `DROP DEFAULT` | `INSTANT` | да | нет |
| `ADD INDEX` / `ADD UNIQUE INDEX` | `INPLACE`, `LOCK=NONE` | да | нет |
| `DROP INDEX` | `INPLACE`, `LOCK=NONE` | да | нет |
| `MODIFY ... NOT NULL` (изменение nullability) | `INPLACE`, `LOCK=NONE` | да | **да** (долго на больших таблицах) |
| Расширение `VARCHAR(N)` в пределах того же размера length-префикса (в utf8mb4 — до 63 символов) | `INPLACE` | да | нет |
| Расширение `VARCHAR(N)` через границу 63 символов, смена типа (`INT → BIGINT`, `TEXT → JSON`), сужение | `COPY` | **нет** (блокировка записи) | да |
| `ADD FOREIGN KEY` | `INPLACE` только при `foreign_key_checks = 0`, иначе `COPY` | да (при `INPLACE`) | нет |
| `DROP FOREIGN KEY` | `INPLACE`, `LOCK=NONE` | да | нет |
| Первый `ADD FULLTEXT INDEX` | `INPLACE`, только `LOCK=SHARED` | нет (запись блокируется) | да |
| `ADD COLUMN ... GENERATED ... VIRTUAL` | `INSTANT` | да | нет |
| `ADD COLUMN ... GENERATED ... STORED` | `COPY` | нет | да |

> Точные ограничения (например, лимит числа `INSTANT`-изменений на строку, `ROW_FORMAT=COMPRESSED`, таблицы с `FULLTEXT`) зависят от версии; перед миграцией на большой таблице проверить matrix в документации MySQL (*Online DDL Operations*) и прогнать на staging с `ALGORITHM` явно.

### 5.1 Универсальный паттерн

```mermaid
flowchart LR
    R0[Release N: код пишет/читает только old_field] --> M1[Migration: ADD COLUMN new_field NULLABLE]
    M1 --> R1[Release N+1: код пишет в new_field И в old_field, читает new_field с fallback]
    R1 --> M2[Migration: BACKFILL new_field из old_field]
    M2 --> R2[Release N+2: код читает только new_field, пишет только new_field]
    R2 --> M3[Migration: DROP COLUMN old_field]
```

Минимум **2–3 релиза** для destructive change. Никогда — drop column в одном релизе с переключением на новое поле.

### 5.2 Добавление nullable поля

Безопасно за один релиз, мгновенно (`INSTANT`):

```php
$this->addSql('ALTER TABLE content_pages ADD COLUMN summary LONGTEXT DEFAULT NULL, ALGORITHM=INSTANT');
```

### 5.3 Добавление NOT NULL поля без default

**Запрещено** делать одной миграцией на непустой таблице без `DEFAULT`: MySQL молча заполнит существующие строки неявным значением типа (`''`, `0`, `'0000-00-00'`-подобные значения), и «пусто» станет неотличимо от реальных данных. Колонку `NOT NULL` с явным `DEFAULT` MySQL добавляет мгновенно (`INSTANT`), существующие строки получают значение по умолчанию — это допустимо, если оно осмысленно.

Если осмысленного default нет, правильный staged-план:

1. Миграция: `ALTER TABLE content_pages ADD COLUMN summary LONGTEXT DEFAULT NULL, ALGORITHM=INSTANT`.
2. Релиз: код начинает писать в `summary` для новых записей.
3. Миграция: `UPDATE content_pages SET summary = '' WHERE summary IS NULL` (или backfill через console command для большой таблицы, батчами по первичному ключу).
4. Миграция: `ALTER TABLE content_pages MODIFY summary LONGTEXT NOT NULL, ALGORITHM=INPLACE, LOCK=NONE` — в MySQL нет `ALTER COLUMN ... SET NOT NULL`: `MODIFY` обязан повторить **полное** определение колонки (тип, charset/collation, default, comment), иначе остальные атрибуты будут потеряны. Операция делает rebuild таблицы online; если остались `NULL`, она упадёт (в strict-режиме).
5. Опционально: `ALTER TABLE content_pages ALTER COLUMN summary SET DEFAULT ('')` — для `LONGTEXT`/`JSON`/`BLOB` литерал `DEFAULT` запрещён, нужно выражение в скобках (MySQL 8.0.13+); для `VARCHAR`/`INT` допустим `SET DEFAULT ''`.

### 5.4 Удаление поля

```mermaid
flowchart LR
    s1[Step 1: код перестаёт писать в old_field] --> s2[Step 2: код перестаёт читать old_field]
    s2 --> s3[Step 3: миграция drop column]
```

Минимум **два релиза**. `DROP COLUMN` в MySQL 8.4 — `INSTANT`, но откатить его нельзя (только restore из backup).

### 5.5 Переименование поля

1. `ADD COLUMN new_name`.
2. Backfill: `UPDATE t SET new_name = old_name` (батчами).
3. Релиз: код читает/пишет в **оба** поля (dual write).
4. Релиз: код читает только `new_name`, пишет в оба.
5. Релиз: код читает/пишет только `new_name`.
6. Миграция: `DROP COLUMN old_name`.

> **Запрещено.** Использовать `ALTER TABLE … RENAME COLUMN …` напрямую в production-релизе с running кодом. В MySQL 8.4 переименование выполняется быстро и online (`INSTANT`/`INPLACE`, без копирования таблицы), но от этого не безопаснее: старый код, выполняющий запрос после DDL, упадёт с `Unknown column 'old_name' in 'field list'` (SQLSTATE 42S22). Expand/contract остаётся обязательным.

### 5.6 Изменение типа поля

Зависит от направления изменения:

- **Расширение внутри одного класса** (`VARCHAR(50) → VARCHAR(60)` в utf8mb4, пока `4 × N ≤ 255` байт) — `INPLACE`. Через границу 63 символов (`VARCHAR(50) → VARCHAR(200)`) MySQL меняет размер length-префикса и пересобирает таблицу (`COPY`, блокировка записи) — на больших таблицах нужен staged plan или online schema change.
- **Расширение числового типа** (`INT → BIGINT`) — всегда `COPY`: staged plan (новая колонка + backfill + switch + drop).
- **Сужение** (`BIGINT → INT`, `VARCHAR(200) → VARCHAR(50)`) — потенциально destructive и в strict-режиме падает на несовместимых данных. Требует staged plan: добавить новое поле, backfill с проверкой, switch код, drop старого.
- **Смена семантики** (`LONGTEXT → JSON`, `VARCHAR → ENUM`) — почти всегда staged: новое поле + backfill через `CAST(old AS JSON)` / `JSON_VALID(old)` + switch + drop. Невалидный JSON при приведении даст ошибку, значения нужно предварительно проверить `SELECT ... WHERE JSON_VALID(col) = 0`.
- `MODIFY` / `CHANGE` всегда повторяют полное определение колонки (см. §5.3).

Пример `VARCHAR → LONGTEXT` (в MySQL это `COPY`, на большой таблице — через staged plan):

```php
$this->addSql('ALTER TABLE content_pages MODIFY meta_description LONGTEXT DEFAULT NULL, ALGORITHM=COPY, LOCK=SHARED');
```

Пример destructive `LONGTEXT → VARCHAR(255)`:

```php
// Запрещено сразу:
// $this->addSql('ALTER TABLE content_pages MODIFY summary VARCHAR(255) DEFAULT NULL');
//
// Правильно:
// 1) Найти строки с длиной > 255: SELECT id FROM content_pages WHERE CHAR_LENGTH(summary) > 255.
// 2) Решить с продуктом, что с ними делать.
// 3) Только потом — MODIFY.
```

### 5.7 Добавление индекса на большую таблицу

В MySQL `CREATE INDEX` / `ALTER TABLE ... ADD INDEX` в InnoDB по умолчанию online: не блокирует чтение и запись (аналог PostgreSQL `CREATE INDEX CONCURRENTLY`). `CONCURRENTLY` в MySQL не существует. Требования — явные `ALGORITHM=INPLACE, LOCK=NONE` и `lock_wait_timeout` (§5.0):

```php
public function up(Schema $schema): void
{
    $this->addSql('SET SESSION lock_wait_timeout = 5');
    $this->addSql('ALTER TABLE content_pages ADD INDEX idx_content_pages_published_at (published_at), ALGORITHM=INPLACE, LOCK=NONE');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE content_pages DROP INDEX idx_content_pages_published_at, ALGORITHM=INPLACE, LOCK=NONE');
}

public function isTransactional(): bool
{
    return false; // DDL в MySQL всё равно коммитится неявно (см. §7)
}
```

Поведение online-создания индекса:

- не блокирует чтение/запись (кроме коротких MDL в начале и конце);
- таблица не копируется, но на больших таблицах создание занимает время и нагружает диск и CPU;
- для `UNIQUE` индекса при наличии дублей операция завершится ошибкой `Duplicate entry` — «невалидного индекса», как в PostgreSQL, остаться не может: при ошибке индекс не создаётся;
- `FULLTEXT` (первый на таблице) блокирует запись (`LOCK=SHARED`) — см. [17-doctrine-and-database](17-doctrine-and-database.md) §15;
- прогресс можно смотреть в `performance_schema.events_stages_current` (при включённых `stage/innodb/alter%`).

### 5.8 Удаление индекса

```php
$this->addSql('ALTER TABLE content_pages DROP INDEX idx_content_pages_old, ALGORITHM=INPLACE, LOCK=NONE');
```

Удаление индекса — только метаданные, быстро. Для осторожности сначала сделать индекс невидимым и понаблюдать за нагрузкой (релиз-цикл), затем удалять:

```sql
ALTER TABLE content_pages ALTER INDEX idx_content_pages_old INVISIBLE;  -- откат: VISIBLE
```

Нельзя удалить индекс, который используется внешним ключом (FK-колонка должна быть проиндексирована) — сначала `DROP FOREIGN KEY`, либо убедиться, что есть другой подходящий индекс.

### 5.9 Foreign keys

- Всегда явный `ON DELETE` (`SET NULL` / `CASCADE` / `RESTRICT`).
- Добавление FK на большую таблицу: при `foreign_key_checks = 1` MySQL использует `COPY` (блокировка записи). Для online-варианта проверку отключают на время одной операции и **обязательно предварительно проверяют отсутствие «сирот»** (аналог `NOT VALID` + `VALIDATE CONSTRAINT`, но валидация — ваша ответственность):

```php
// Миграция должна быть isTransactional() === false: foreign_key_checks нельзя переключить внутри транзакции.
// 1) Проверка: должно вернуть 0 строк.
//    SELECT COUNT(*) FROM content_pages p LEFT JOIN users u ON u.id = p.author_id
//    WHERE p.author_id IS NOT NULL AND u.id IS NULL;
$this->addSql('SET SESSION lock_wait_timeout = 5');
$this->addSql('SET SESSION foreign_key_checks = 0');
$this->addSql('ALTER TABLE content_pages ADD CONSTRAINT fk_content_pages_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL, ALGORITHM=INPLACE, LOCK=NONE');
$this->addSql('SET SESSION foreign_key_checks = 1');
```

- Типы и collation колонок FK и родительского ключа должны совпадать (`BINARY(16)` для ULID), иначе MySQL вернёт ошибку 3780/1215.
- Имя FK уникально в пределах БД; имя индекса — в пределах таблицы.
- `DROP FOREIGN KEY` — `ALTER TABLE t DROP FOREIGN KEY fk_name` (не `DROP CONSTRAINT`, хотя оно поддерживается в 8.0.19+).

### 5.10 Большой UPDATE/DELETE

Не делать миграцией на миллионах строк — долгая транзакция держит row/gap locks, раздувает undo log и блокирует репликацию/бэкап. Использовать:

- отдельный console command, обрабатывающий батчами по 1 000–10 000 строк (по диапазону первичного ключа, а не `LIMIT/OFFSET`);
- запуск вне окна деплоя;
- логирование прогресса;
- повтор транзакции батча при `DeadlockException`/`LockWaitTimeoutException`.

```php
// Запрещено внутри миграции:
// $this->addSql('UPDATE content_pages SET status = ... WHERE ...');  // 5M строк → блокировка

// Правильно:
// Создать app:content:backfill-status console command,
// запустить отдельно после деплоя.
```

---

## 6. Что НЕЛЬЗЯ делать в миграции

- Импортировать и использовать `App\Module\*\Domain\*` (entities, services). Миграция работает только с DBAL/SQL.
- Полагаться на присутствие любых данных (могут быть тестовые миграции с пустой БД).
- Делать долгие data-миграции inline на большой таблице (см. §5.10).
- Менять файл миграции, которая уже была применена в production.
- Удалять файл миграции, которая записана в `doctrine_migration_versions`.
- Использовать `EntityManager`, `Repository`, `Container` внутри `up()`/`down()`.
- Зависеть от текущего времени без зафиксированного значения (для тестируемости).
- Делать `DROP TABLE` с данными без явного «expand/contract → drop» staged plan.
- Полагаться на откат DDL при ошибке: в MySQL он невозможен (§7.1).
- Использовать PostgreSQL-специфичный SQL (`CONCURRENTLY`, `ON CONFLICT`, `RETURNING`, `ILIKE`, `SET NOT NULL`, `ALTER COLUMN ... TYPE`, partial indexes) — миграции пишутся только на диалекте MySQL 8.4.

---

## 7. up() / down()

### 7.1 DDL в MySQL не транзакционен

Любой DDL-оператор (`CREATE/ALTER/DROP/TRUNCATE/RENAME`) в MySQL завершает текущую транзакцию неявным `COMMIT` и не откатывается. Следствия для миграций:

- Doctrine Migrations по умолчанию оборачивает миграцию в транзакцию, но для DDL она бесполезна: транзакция фиксируется на первом же DDL, а в конце Doctrine пытается сделать `commit()` уже закрытой транзакции. Это работает, но Doctrine выдаёт deprecation (*«the transaction is already committed»*). Правильный способ — объявлять в DDL-миграциях `isTransactional(): false` (или выставить `transactional: false` в `config/packages/doctrine_migrations.yaml` глобально).
- **Если миграция из нескольких операторов упала на середине, предыдущие операторы уже применены**, а версия в `doctrine_migration_versions` не записана. Повторный запуск упадёт на первом же операторе (`Table already exists`, `Duplicate column name`, `Duplicate key name`).
- Поэтому: **один–два DDL-оператора на миграцию**; смешивать DDL и массовый DML в одной миграции нельзя; перед боевой миграцией — обязательный `mysqldump` (делает deploy-скрипт, см. §9.1).
- Начальная миграция `Version20261004000100` создаёт 16 таблиц и поэтому при частичном сбое оставляет БД в промежуточном состоянии; на пустой БД (dev/CI/новый стенд) это решается `make reset-db`, на production — restore из backup.

### 7.2 Идемпотентность

В MySQL `IF NOT EXISTS` / `IF EXISTS` поддерживается только для `CREATE TABLE`, `DROP TABLE`, `CREATE DATABASE`, `DROP DATABASE` (и `CREATE/DROP INDEX` отсутствует; `ADD COLUMN IF NOT EXISTS` — только в MariaDB). Для остальных операций идемпотентность обеспечивается явной проверкой через `information_schema` в `up()` и пропуском шага:

```php
public function up(Schema $schema): void
{
    $exists = (int) $this->connection->fetchOne(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        ['content_pages', 'idx_content_pages_published_at'],
    );

    $this->skipIf($exists > 0, 'Index idx_content_pages_published_at already exists.');
    $this->addSql('ALTER TABLE content_pages ADD INDEX idx_content_pages_published_at (published_at), ALGORITHM=INPLACE, LOCK=NONE');
}
```

(`$this->connection` — DBAL-соединение миграции; это допустимо, ORM/Domain при этом не используются.)

### 7.3 up() и down()

- `up()` обязателен; DDL-операторы — маленькие и по возможности идемпотентные (§7.2).
- `down()` — best effort. Может быть пустым с комментарием `// not reversible`, если откат обоснованно невозможен (например, `DROP COLUMN` с данными).
- Для production катастрофических случаев откат — это restore из backup, не `down()` (см. [36-backup-restore](36-backup-restore.md), [37-runbooks](37-runbooks.md) §15). `down()` также не транзакционен.
- Если `down()` пустой — всё равно объявить метод, чтобы не падало.
- При `down()` для `CREATE TABLE` с FK — сначала `DROP FOREIGN KEY`, затем `DROP TABLE` (как в `Version20261004000100`).

```php
public function down(Schema $schema): void
{
    // Not reversible — see backup restore in docs/36-backup-restore.md
}
```

---

## 8. Тестирование миграций

### 8.1 В CI

- `composer test` и CI поднимают MySQL 8.4 как service-контейнер (см. [35-cicd](35-cicd.md)).
- Шаги:
    1. `doctrine:migrations:status` — показ всех миграций.
    2. `doctrine:migrations:migrate --env=test --allow-no-migration --no-interaction` — применение на свежую БД.
    3. `doctrine:migrations:migrate first` и повторный `migrate` — проверка `down()` всех миграций: откат до пустой схемы и повторное применение.
    4. `doctrine:schema:validate --env=test` — полная синхронизация attributes ↔ схемы (без `--skip-sync`); легитимные исключения (`path_active`, `messenger_messages`) описаны в [17-doctrine-and-database](17-doctrine-and-database.md) §13.1.
    5. `phpunit` — функциональные/интеграционные тесты на новой схеме.

### 8.2 Локально перед коммитом

Миграции проверяются только на MySQL из Docker Compose; SQLite запрещён (поведение DDL, JSON, collation и индексов отличается).

```bash
make reset-db            # пересоздать dev-БД с нуля: drop/create + migrate + fixtures
make test-db             # создать zaborprofil_test и выдать права
make migrate             # применить миграции к dev-БД
docker compose exec app php bin/console doctrine:schema:validate
make test
```

Дополнительно для миграций на уже заполненной таблице: загрузить dev-данные, выполнить `make migrate` и сравнить `SHOW CREATE TABLE <table>` до и после; проверить `down()` на копии БД.

### 8.3 На staging

- Staging deploy запускает миграции автоматически.
- После staging deploy — visual smoke на ключевые admin/публичные страницы.
- Если миграция критическая (большая таблица, смена типа, drop, FK) — ручная проверка `doctrine:migrations:status` и `SHOW CREATE TABLE` после.
- Staging на хостинге Beget использует MySQL хостинга: права пользователя БД и значения глобальных переменных (`innodb_*`, `lock_wait_timeout`) могут отличаться от Docker — проверить, что операция выполняется с явным `ALGORITHM`/`LOCK` и под выданными правами.

---

## 9. Production deployment миграций

### 9.1 Что делает deploy-скрипт

1. `tools/deploy/deploy-production.sh` снимает дамп: `mysqldump --single-transaction --routines --triggers --no-tablespaces --default-character-set=utf8mb4 <db> | gzip -9 > shared/backups/db/<release>_database.sql.gz` и проверяет архив `gzip -t`. Учётные данные берутся из `DATABASE_URL` через временный defaults-файл (пароль не попадает в argv процессов).
2. Применяет миграции: `php bin/console doctrine:migrations:migrate --no-interaction`.
3. Если миграция падает — release **не переключается** на `current`. Старый release продолжает обслуживать. **Но схема БД может остаться частично изменённой** (DDL в MySQL не откатывается, см. §7.1).
4. Дальше: `cache:clear` → `cache:warmup` → permissions → `current` switch → `systemctl reload php8.5-fpm` → `health-check.sh`.

### 9.2 Что делать, если миграция упала

См. [37-runbooks](37-runbooks.md) §14 («Doctrine migrations failed») и §15 («Миграции применились частично»).

Кратко:

1. **Не паниковать.** Если `current` не переключился — production обслуживает старый release (старый код совместим со схемой по правилам expand/contract §5).
2. Прочитать `doctrine:migrations:status` — что применилось, что нет.
3. Прочитать SQL миграции в `migrations/Version<TS>.php` — какой шаг упал, и сверить с фактической схемой: `SHOW CREATE TABLE <table>\G`, `information_schema.COLUMNS` / `STATISTICS`. В MySQL **операторы, выполненные до ошибки, остаются применёнными**.
4. Если миграция применилась частично (см. [37-runbooks](37-runbooks.md) §15) — снять полный `mysqldump` **немедленно**, дальше следовать runbook. Типовые пути: (а) восстановиться из дампа, снятого deploy-скриптом перед миграцией, и выпустить исправленную миграцию; (б) вручную довести схему до целевого состояния и пометить версию выполненной (`doctrine:migrations:version <FQCN> --add`) — только по решению архитектора и после проверки схемы.
5. Если миграция явно неверная — поправить в коде, собрать новый release, повторить.
6. Никогда не править файл уже-применённой миграции.
7. Если `ALTER` завис (`Waiting for table metadata lock`) — найти блокирующую транзакцию (`SELECT * FROM information_schema.INNODB_TRX`, `sys.schema_table_lock_waits`), завершить её (`KILL <thread_id>`) по согласованию; при истечении `lock_wait_timeout` миграция упадёт без изменения таблицы и её можно повторить.

### 9.3 Откат миграции на production

Откат БД через `down()` запрещён в production без явного решения архитектора. Правильный путь:

1. Откатить **release** (переключить `current` на предыдущий) — см. [34-deployment](34-deployment.md), [37-runbooks](37-runbooks.md) §42.
2. Если код предыдущего release несовместим с новой схемой — определить через `expand/contract`, должны быть совместимы. Если несовместимы — это нарушение §5.
3. Если несовместимость абсолютна и данные испорчены — restore из backup до миграции (`gzip -dc <release>_database.sql.gz | mysql <db_restored>`, см. [36-backup-restore](36-backup-restore.md)).

---

## 10. Чек-лист миграции

- [ ] `getDescription()` заполнено осмысленно.
- [ ] Diff проверен глазами; нет лишних DDL.
- [ ] Backward-compatible (или явно объявлено staged через 2+ релиза).
- [ ] Имена индексов/таблиц соответствуют конвенции (`idx_*`, `uniq_*`, `<module>_<resource>`).
- [ ] FK — с явным `ON DELETE`; типы FK-колонок совпадают с родительским ключом (`BINARY(16)` для ULID).
- [ ] У каждой `ALTER TABLE` на существующей таблице явно указаны `ALGORITHM` (и `LOCK`, где применимо); `COPY` на крупной таблице не используется.
- [ ] Перед DDL на нагруженной таблице выставлен `SET SESSION lock_wait_timeout` (MDL).
- [ ] DDL-миграция: `isTransactional() === false`; один–два оператора на миграцию (DDL в MySQL не откатывается).
- [ ] Не смешаны DDL и массовый DML в одной миграции.
- [ ] Не импортирует `App\Module\*\Domain\*`, не использует `EntityManager`.
- [ ] `up()` и (по возможности) `down()` написаны.
- [ ] Идемпотентность: `CREATE TABLE IF NOT EXISTS` / `DROP TABLE IF EXISTS` или проверка через `information_schema` + `skipIf()`.
- [ ] Нет destructive change без staged plan.
- [ ] Нет inline-миграции данных на большой таблице (выделено в console command).
- [ ] `MODIFY`/`CHANGE` содержит полное определение колонки (тип, `NOT NULL`, default, charset/collation, comment).
- [ ] Прогон в CI прошёл (`status` + `migrate` + `schema:validate`).
- [ ] Backup-этап на production предусмотрен deploy-скриптом (`mysqldump` перед миграцией).
- [ ] Документация модели в [05-domain-model](05-domain-model.md) обновлена.
- [ ] Entity ↔ schema проверка (`doctrine:schema:validate`) — без ошибок.
- [ ] Repository тесты обновлены (если меняется набор колонок/типов).

---

## 11. Чек-лист production-safe миграции (расширенный)

Для миграций с риском downtime / потери данных дополнительно:

- [ ] Размер таблицы оценён:

  ```sql
  SELECT table_name,
         ROUND((data_length + index_length) / 1024 / 1024, 1) AS size_mb,
         table_rows
  FROM information_schema.TABLES
  WHERE table_schema = DATABASE() AND table_name = 'content_pages';
  ```
- [ ] Если таблица > 1 GB или > 1 М строк — план обязательно через online DDL (`ALGORITHM=INPLACE/INSTANT`, `LOCK=NONE`), batched updates либо внешний online schema change (`gh-ost`, `pt-online-schema-change`). `ALGORITHM=COPY` на такой таблице запрещён.
- [ ] Выбранный алгоритм подтверждён: операция с явным `ALGORITHM` на staging не упала (если MySQL не может выполнить её заданным способом — вернёт ошибку до начала работы).
- [ ] Прорепетировано на staging с **production-like объёмом данных** (восстановление prod-дампа в staging или в отдельную БД через `tools/deploy/restore-rehearsal.sh`, см. [36-backup-restore](36-backup-restore.md)).
- [ ] Замерено время выполнения на staging.
- [ ] Таймауты: `SET SESSION lock_wait_timeout = 5` (ожидание MDL), `innodb_lock_wait_timeout` для DML-батчей; помнить, что `max_execution_time` ограничивает только `SELECT`, а не DDL. Проверен `innodb_online_alter_log_max_size` для таблиц с интенсивной записью.
- [ ] Нет долгих транзакций на целевой таблице в момент старта DDL (`information_schema.INNODB_TRX`).
- [ ] Если миграция длится > 1 минуты — окно обслуживания (maintenance mode, см. [37-runbooks](37-runbooks.md) §62).
- [ ] Retention backup’ов проверен: последний дамп доступен и целостен (`gzip -t <файл>.sql.gz`), пробное восстановление в отдельную БД проходит.
- [ ] Stakeholder уведомлён, согласовано окно деплоя.
- [ ] Rollback-план зафиксирован письменно (учтено, что DDL необратим без restore).
- [ ] Дежурный готов мониторить `prod.log`, `SHOW FULL PROCESSLIST` / `performance_schema.metadata_locks`, `messenger:stats` в первые 30 минут после деплоя.

---

## 12. Anti-patterns

| Антипаттерн | Почему плохо | Что делать |
|---|---|---|
| `DROP COLUMN` в первом релизе после переключения кода | Старый код в момент release switch падает | Staged: dual-write → switch → drop |
| `ALTER TABLE … ADD COLUMN x NOT NULL` без default на непустой таблице | Существующие строки молча получают неявное значение (`''`, `0`) | NULLABLE (или осмысленный `DEFAULT`) → backfill → `MODIFY ... NOT NULL` |
| `ALTER TABLE` без `ALGORITHM`/`LOCK` на большой таблице | MySQL может выбрать `COPY` и заблокировать запись | Явно `ALGORITHM=INSTANT` / `INPLACE, LOCK=NONE` — при невозможности MySQL вернёт ошибку до начала |
| DDL без `lock_wait_timeout` | `ALTER` ждёт MDL за долгой транзакцией, все новые запросы к таблице встают в очередь | `SET SESSION lock_wait_timeout = 5`, проверка `INNODB_TRX` |
| `ADD FOREIGN KEY` на большой таблице при `foreign_key_checks = 1` | `COPY`, блокировка записи | Проверить сирот, `foreign_key_checks = 0` на время `ALGORITHM=INPLACE` |
| `MODIFY col type` без полного определения колонки | Теряются `NOT NULL`, default, collation, comment | Перечислять полное определение |
| Многооператорная DDL-миграция | При сбое на середине схема остаётся частично изменённой | 1–2 оператора на миграцию, `isTransactional() === false`, дамп перед деплоем |
| Ожидание, что миграцию можно откатить транзакцией | DDL в MySQL коммитится неявно | Backup + restore, staged expand/contract |
| `UPDATE big_table SET …` в миграции | Длинная транзакция, блокировка | Console command, batched, вне миграции |
| `RENAME COLUMN` в одном релизе | Старый код падает (`Unknown column`) | ADD new + dual-write + switch + DROP old |
| Изменение применённой миграции | Расхождение `doctrine_migration_versions` со схемой | Создать новую миграцию, не править старую |
| `EntityManager` внутри миграции | Зависимость от текущей версии Domain — миграция сломается через 6 месяцев | Только DBAL SQL |
| `DROP TABLE` без backfill в новую структуру | Безвозвратная потеря данных | Backfill → switch → drop в отдельных релизах |
| `down()` пустой без комментария | Непонятно, обратимо или нет | `// not reversible — see docs/36-backup-restore.md` |
| `doctrine:schema:update --force` | Обходит миграции; следующая миграция упадёт на расхождении | Запрещено везде |

---

## 13. Rollback considerations

- Нельзя «откатить» миграцию, которая удалила данные или изменила схему (DDL необратим транзакционно). Откат = restore из backup + repoint.
- Перед production-миграцией всегда есть свежий backup (`tools/deploy/deploy-production.sh` делает `mysqldump --single-transaction ... | gzip -9` в `shared/backups/db/<release>_database.sql.gz` и проверяет `gzip -t`).
- Retention backup’ов — минимум 14 дней (см. [36-backup-restore](36-backup-restore.md)).
- Restore — в **отдельную** БД (`<db>_restored`), не поверх production:

  ```bash
  mysql -e "CREATE DATABASE zaborprofil_restored CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
  gzip -dc shared/backups/db/<release>_database.sql.gz | mysql zaborprofil_restored
  ```

  Затем — переключение через смену `DATABASE_URL`. Репетиция восстановления автоматизирована в `tools/deploy/restore-rehearsal.sh` (временная БД `<db>_restore_rehearsal_<ts>`).

---

## 14. Чек-лист для AI-агента при изменении БД

Перед изменением:

- [ ] Прочитан этот документ + [17-doctrine-and-database](17-doctrine-and-database.md) + [05-domain-model](05-domain-model.md).
- [ ] Классифицировано как «Database schema change» или «Doctrine entity change» (см. [41-implementation-playbook](41-implementation-playbook.md) §13).
- [ ] Составлен planning note (см. [41-implementation-playbook](41-implementation-playbook.md) §5).
- [ ] Определена стратегия: одношаговая или staged через 2+ релиза.
- [ ] Оценён размер таблицы (хотя бы примерно, по [05-domain-model](05-domain-model.md)) и выбран алгоритм DDL (`INSTANT` / `INPLACE` / не `COPY`, §5.0).

Во время изменения:

- [ ] Миграция создана через `doctrine:migrations:diff` и просмотрена глазами; диалект — только MySQL 8.4 (без `CONCURRENTLY`, `ON CONFLICT`, `ILIKE`, `RETURNING`, partial indexes, `jsonb`-операторов).
- [ ] Миграция работает только с DBAL/SQL, не импортирует Domain.
- [ ] Заполнен `getDescription()`.
- [ ] Для DDL — `isTransactional() === false`, явные `ALGORITHM`/`LOCK`, `lock_wait_timeout`.
- [ ] Entity-attributes согласованы с миграцией.
- [ ] Repository обновлён, если поменялся контракт.
- [ ] Тесты обновлены.

После изменения:

- [ ] `doctrine:migrations:status` — показывает новую миграцию.
- [ ] `doctrine:migrations:migrate --env=test` — успешно (на MySQL из Docker Compose).
- [ ] `doctrine:schema:validate` — без ошибок.
- [ ] `composer test` — зелёный.
- [ ] Документация в [05-domain-model](05-domain-model.md) обновлена.
- [ ] План rollback зафиксирован в planning note.

Запрещено:

- Менять файл уже-применённой миграции.
- Делать destructive change в одном релизе с переключением кода.
- Использовать `doctrine:schema:update --force`.
- Делать `UPDATE`/`DELETE` миллионов строк в `up()`.
- Импортировать Domain в миграцию.
- Запускать миграции на SQLite или на любой СУБД, кроме MySQL.

---

## 15. Связанные документы

- [05-domain-model](05-domain-model.md) — что меняется в модели вместе со схемой.
- [17-doctrine-and-database](17-doctrine-and-database.md) — правила маппинга, индексов, типов.
- [34-deployment](34-deployment.md) — место миграций в release-процессе.
- [35-cicd](35-cicd.md) — как миграции проверяются в CI.
- [36-backup-restore](36-backup-restore.md) — стратегия backup и restore при инциденте.
- [37-runbooks](37-runbooks.md) — инциденты 14 (failed) и 15 (партикл).
- [41-implementation-playbook](41-implementation-playbook.md) §13 — DB schema change playbook.
