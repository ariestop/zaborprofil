# 17. Doctrine и база данных

> **Назначение документа.** Зафиксировать правила работы с MySQL 8.4 и Doctrine ORM/DBAL в проекте `zaborprofil`. Покрывает маппинг, naming, индексы, транзакции, JSON, soft delete, audit-поля, оптимизацию запросов.
>
> **Аудитория.** Backend-разработчики, AI-агенты, DBA, тимлиды.
>
> **Связанные документы.** [18-migrations](18-migrations.md), [05-domain-model](05-domain-model.md), [10-domain-layer](10-domain-layer.md), [11-infrastructure-layer](11-infrastructure-layer.md), [04-layer-rules](04-layer-rules.md), [ADR-0002](adr/0002-mysql-as-main-database.md), [ADR-0004](adr/0004-doctrine-orm-usage.md).

---

## 1. Конфигурация

`config/packages/doctrine.yaml`:

- DBAL: `url: '%env(resolve:DATABASE_URL)%'`, `driver: pdo_mysql`, `server_version: '8.4'`, `charset: utf8mb4`.
- DBAL `default_table_options`: `charset: utf8mb4`, `collation: utf8mb4_0900_ai_ci`, `engine: InnoDB` — применяются ко всем таблицам, которые генерирует Doctrine (`CREATE TABLE ... DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE = InnoDB`).
- ORM: `naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware`, `validate_xml_mapping: true`.
- Маппинг: `type: attribute`, `dir: %kernel.project_dir%/src`, `prefix: App`, `alias: App`.
- В `prod`: query/result cache pools на filesystem-кэше (`doctrine.system_cache_pool` → `cache.system`, `doctrine.result_cache_pool` → `cache.app`, см. [23-cache](23-cache.md)).

Формат `DATABASE_URL`:

```dotenv
DATABASE_URL="mysql://user:pass@host:3306/zaborprofil?serverVersion=8.4&charset=utf8mb4"
```

> **Важно.** `serverVersion=8.4` в URL и `server_version: '8.4'` в `doctrine.yaml` должны совпадать с реальной мажорной версией MySQL: по ней Doctrine выбирает платформу (диалект MySQL 8.x) и синтаксис DDL. Пароль со спецсимволами в URL нужно url-кодировать (`@` → `%40`, `/` → `%2F`).

> **Фактическое состояние.** В проекте используется Doctrine ORM 3, DBAL 4, doctrine-bundle 3.x (см. [composer.json](../composer.json)). Маппинг — только attributes. Result cache на filesystem-пуле включается в prod-окружении.

---

## 2. MySQL: версия и расширения

| Что | Значение |
|---|---|
| Версия | `>= 8.4` (LTS) |
| Движок хранения | только InnoDB (транзакции, FK, row-level locking) |
| Кодировка / collation | `utf8mb4` / `utf8mb4_0900_ai_ci` (задаётся на уровне сервера в `docker/mysql/my.cnf`, на уровне Doctrine — в `default_table_options`) |
| Минимальный extension set PHP | `pdo_mysql`, `intl`, `mbstring` |
| JSON | нативный тип `JSON`; используется для `PageBlock.content`/`settings`, `Setting.value`, `Page.json_ld`, snapshot-полей ревизий |
| ULID | через `Symfony\Component\Uid\Ulid`, тип Doctrine `'ulid'`; в MySQL хранится как `BINARY(16)` |
| Partial unique index | **не поддерживается**; аналог — unique-индекс по generated column (см. §13) |
| Транзакционный DDL | **нет**: любой `CREATE/ALTER/DROP` завершает текущую транзакцию неявным `COMMIT` (см. [18-migrations](18-migrations.md) §7) |
| Режим SQL | серверный по умолчанию (`STRICT_TRANS_TABLES`, `ONLY_FULL_GROUP_BY` и др.); не отключать |
| Желательные возможности (целевое) | `performance_schema` + схема `sys` (анализ медленных запросов), slow query log, `FULLTEXT` с `ngram`-парсером (поиск по каталогу) |

> **Локально.** `pdo_mysql` обязательно включён в PHP-установке; в Docker оно уже входит в PHP runtime (см. [AGENTS.md](../AGENTS.md)). Сервер MySQL локально поднимается Docker-сервисом `mysql` (`mysql:8.4`).

### 2.1 Соответствие типов Doctrine и MySQL

| Doctrine type | MySQL | Примечание |
|---|---|---|
| `string` (length N) | `VARCHAR(N)` | N — в символах; в индексе занимает до `4 × N` байт (utf8mb4) |
| `text` | `LONGTEXT` | не индексируется целиком (только prefix / `FULLTEXT`) |
| `integer` / `bigint` | `INT` / `BIGINT` | |
| `boolean` | `TINYINT` (0/1) | в SQL-запросах сравнивать с `1`/`0` |
| `datetime_immutable` | `DATETIME` | без часового пояса и без долей секунд; единую timezone (рекомендуется UTC) обеспечивает приложение |
| `json` | `JSON` | без опции `jsonb`; см. §11 |
| `ulid` | `BINARY(16)` | см. §4 |
| `decimal` | `DECIMAL(p, s)` | для денег — только `DECIMAL` или целое в минимальных единицах (`price_cents`) |

### 2.2 Collation `utf8mb4_0900_ai_ci`

Collation влияет на сравнение строк в `WHERE`, `ORDER BY` и **unique-индексах**:

- `_ci` — регистронезависимая: `/About` и `/about` считаются одним значением; `LIKE` без `ILIKE` тоже регистронезависим.
- `_ai` — **акцент-нечувствительная**: `е` = `ё`, `a` = `á`. Два пути, отличающихся только акцентами, нарушат unique-индекс.
- `0900` — `NO PAD`: концевые пробелы значимы (в отличие от старых `utf8mb4_unicode_ci`).
- Для значений, где регистр/акценты принципиальны (например, токены), задавать `COLLATE utf8mb4_bin` или `BINARY`/`VARBINARY` колонку явно: `#[ORM\Column(type: 'string', length: 64, options: ['collation' => 'utf8mb4_bin'])]`.
- Для `slug`/`path` поведение `_ci` — желаемое: URL приводятся к нижнему регистру на уровне Entity, а БД дополнительно страхует от дублей, отличающихся регистром.

### 2.3 Лимиты, о которых нужно помнить

- Максимальная длина ключа индекса в InnoDB (`ROW_FORMAT=DYNAMIC`) — 3072 байта. `VARCHAR(512)` в utf8mb4 = 2048 байт — индексируется целиком; `VARCHAR(1024)`/`VARCHAR(2048)` (`target_path`, `canonical_url`) целиком в unique-индекс не помещаются — использовать prefix-индекс (`INDEX (target_path(191))`) или хэш-колонку.
- Максимальный размер строки — 65 535 байт (без учёта `TEXT`/`BLOB`/`JSON`, которые хранятся отдельно).
- Каждая строка в utf8mb4 может занимать до 4 байт на символ; кириллица — 2 байта.
- Размер значения `JSON`/`LONGTEXT` ограничен `max_allowed_packet` (по умолчанию 64 MB в MySQL 8.4).

---

## 3. Naming strategy

`underscore_number_aware`:

- `Page` → `pages` (имена таблиц задаём явно через `#[ORM\Table]`).
- `sortOrder` → `sort_order`.
- `publishedAt` → `published_at`.

### 3.1 Конвенции имён

| Объект | Конвенция | Пример |
|---|---|---|
| Таблица | `<module>_<resource>` или нейтральное | `content_pages`, `seo_redirects`, `settings` |
| Колонка | `snake_case` | `published_at`, `sort_order`, `is_active` |
| Первичный ключ | `id` | `id BINARY(16) PRIMARY KEY` (ULID) |
| Foreign key column | `<entity>_id` | `page_id`, `author_id` |
| FK constraint | `fk_<table>_<column>` | `fk_content_page_blocks_page_id` (имена FK уникальны в пределах всей БД) |
| Обычный индекс | `idx_<table>_<columns>` | `idx_content_pages_status` |
| Уникальный индекс | `uniq_<table>_<columns>` | `uniq_catalog_products_path` |
| Unique «по условию» (generated column) | `uniq_<table>_<columns>_<predicate>` | `uniq_content_pages_path_active` (генерируемая колонка `path_active`, `NULL` для удалённых) |
| Индекс по JSON-выражению | `idx_<table>_<json_path>` | `idx_content_page_blocks_heading` |
| FULLTEXT-индекс | `ft_<table>_<columns>` | `ft_catalog_products_search` |
| Boolean | `is_<x>` или `has_<x>` | `is_active`, `has_blocks` |
| Timestamps | `created_at`, `updated_at`, `deleted_at`, `published_at` | — |

> **Запрещено.** Имена таблиц/колонок на кириллице, CamelCase в БД, неконсистентные суффиксы (`_dt` vs `_at`), сокращения без причины (`pg` вместо `page`), имена, совпадающие с зарезервированными словами MySQL, без необходимости (если колонка названа как keyword — Doctrine экранирует её обратными кавычками, а в нативном SQL это нужно делать вручную).

---

## 4. Identifiers

Используется ULID:

- 26-символьная сортируемая строка (`01HABCDEF...`) в PHP/JSON/URL; в MySQL — `BINARY(16)`.
- Сортируется по времени, как `AUTO_INCREMENT`, без блокировок счётчика и без round-trip к БД.
- Критично для InnoDB: первичный ключ является **clustered index**, строки физически упорядочены по PK. Последовательные ULID дают append-вставки в конец B-tree, а random UUID v4 — page split'ы и фрагментацию.
- Каждый secondary-индекс InnoDB неявно содержит PK, поэтому компактные 16 байт (а не `CHAR(26)` = 104 байта в utf8mb4) напрямую уменьшают размер всех индексов.
- Безопаснее для публичных URL, чем `bigint` (не раскрывает порядок и количество записей).
- Тип Doctrine: `'ulid'` (`Symfony\Component\Uid\Ulid`). Платформа MySQL не имеет нативного GUID-типа, поэтому `UlidType` использует `BINARY(16)` (в PostgreSQL использовался бы `uuid`).

```php
#[ORM\Id]
#[ORM\Column(type: 'ulid', unique: true)]
private Ulid $id;

public function __construct()
{
    $this->id = new Ulid();
}
```

Единственное исключение — техническая таблица `messenger_messages` (`BIGINT AUTO_INCREMENT`), которой управляет Symfony Messenger.

Ссылки на пользователей в audit-полях (`created_by`, `updated_by`, `published_by`) хранятся как `VARCHAR(26)` (строковое представление ULID) без внешнего ключа.

### 4.1 Работа с ULID в SQL

В консоли `mysql` идентификатор виден как бинарные данные. Для отладки:

```sql
-- Показать id в hex:
SELECT HEX(id) AS id_hex, path FROM content_pages LIMIT 5;

-- Найти по hex:
SELECT * FROM content_pages WHERE id = UNHEX('<32 hex-символа>');
```

Перевод между ULID-строкой и hex делает PHP (`Ulid::fromString('01H...')->toHex()`, `Ulid::fromBinary(hex2bin($hex))->toBase32()`); средствами MySQL base32-Crockford не конвертируется. В нативных SQL-запросах (DBAL) значение передавать как `$ulid->toBinary()` с `ParameterType::BINARY`.

> **Целевое.** ADR-0011 на ULID-стратегию (см. [adr/0011-ulid-identifiers](adr/0011-ulid-identifiers.md)).

---

## 5. Маппинг

- Маппинг — **только** через PHP-attributes (`#[ORM\Entity]`, `#[ORM\Column]` и т.д.).
- Никаких XML/YAML маппингов.
- Имена таблиц — явные (`content_pages`, `seo_redirects`, `settings`).
- Имена индексов — явные (`idx_content_pages_status`, `uniq_content_pages_path_active`).
- Repository class указывается через `repositoryClass:` в `#[ORM\Entity]`, но в Domain хранится только `*RepositoryInterface`. Doctrine repository живёт в Infrastructure (см. [04-layer-rules](04-layer-rules.md)).

Пример:

```php
#[ORM\Entity]
#[ORM\Table(name: 'content_pages')]
#[ORM\Index(name: 'idx_content_pages_status', columns: ['status'])]
final class Page
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    #[ORM\Column(type: 'string', length: 512)]
    private string $path;

    // ...
}
```

> **Anti-pattern.** Имена через автогенерацию Doctrine (`IDX_AB12CD34`, `FK_AB12CD34`). Они нечитаемы при инциденте и при просмотре `SHOW PROCESSLIST`/`EXPLAIN`.

> **Фактическое состояние.** В начальной миграции `Version20261004000100` явные имена (`idx_*`, `uniq_*`) заданы для всех индексов, объявленных в attributes; FK-constraints и служебные индексы под FK-колонки получили имена, сгенерированные Doctrine (`FK_<hash>`, `IDX_<hash>`). Для новых связей имена задавать явно.

---

## 6. Связи

- `ManyToOne` всегда с `nullable`/`onDelete` явно.
- `OneToMany` — обычно с `cascade: ['persist', 'remove']` и `orphanRemoval: true`, если коллекция — часть aggregate.
- `ManyToMany` — избегать; лучше явная связь через ассоциативную сущность.
- `OneToOne` — редко; обычно лучше композиция в одной сущности или embedded.

```php
#[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'blocks')]
#[ORM\JoinColumn(name: 'page_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
private Page $page;

#[ORM\OneToMany(mappedBy: 'page', targetEntity: PageBlock::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
#[ORM\OrderBy(['sortOrder' => 'ASC'])]
private Collection $blocks;
```

> **Запрещено.** ManyToMany без promoted ассоциативной сущности. `cascade: ['all']` (слишком широко). Lazy-loaded коллекции в публичных API responders без явной prefetch.

---

## 7. Транзакции

### 7.1 Границы транзакции

- **Транзакционная граница — Application handler** (см. [09-application-layer](09-application-layer.md)).
- `Repository::save()` обычно делает `persist + flush` для одной агрегатной сущности.
- Если в одном handler нужно сохранить две сущности атомарно — использовать явную транзакцию через `EntityManager::wrapInTransaction()` или (целевое) `UnitOfWorkInterface`.

```php
// Application/Handler/PublishPageHandler.php
final readonly class PublishPageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private RedirectRepositoryInterface $redirects,
        private TransactionalRunner $tx,            // целевая абстракция над EM
        private LoggerInterface $logger,
    ) {}

    public function __invoke(PublishPageCommand $cmd): void
    {
        $this->tx->run(function () use ($cmd): void {
            $page = $this->pages->getById($cmd->pageId);
            $page->publish($cmd->publishedAt);
            $this->pages->save($page);

            if ($cmd->oldPath !== null && $cmd->oldPath !== $page->path()) {
                $this->redirects->save(Redirect::permanent($cmd->oldPath, $page->path()));
            }
        });

        $this->logger->info('page.published', ['page_id' => (string) $page->id()]);
    }
}
```

### 7.2 Anti-patterns

- Транзакция в контроллере (`$em->beginTransaction()` в `Controller::action`) — нарушение [04-layer-rules](04-layer-rules.md).
- Вложенная транзакция (`beginTransaction` внутри другой transaction) без `SAVEPOINT` — нестабильное поведение.
- Долгие транзакции (минуты) — удерживают row/gap locks, раздувают undo log (рост `history list length`) и приводят к `Lock wait timeout exceeded` (1205) у других запросов.
- DDL внутри транзакции: в MySQL любой `CREATE/ALTER/DROP/TRUNCATE` делает неявный `COMMIT`; миграции и бизнес-код не должны рассчитывать на откат DDL (см. [18-migrations](18-migrations.md) §7).
- Внешний HTTP-вызов внутри транзакции — таймаут блокирует БД.
- `flush()` в цикле — N round-trips к БД. Использовать batch flush (`flush + clear` каждые 100 entities).

### 7.3 Уровень изоляции, deadlocks и lock wait

- Уровень изоляции InnoDB по умолчанию — `REPEATABLE READ` (consistent snapshot на первой чтении транзакции, gap locks при `SELECT ... FOR UPDATE`). Менять его глобально не нужно.
- Конкурентные транзакции могут завершиться **deadlock** (`SQLSTATE 40001`, код 1213: `Deadlock found when trying to get lock`) — InnoDB откатывает одну из транзакций. Doctrine выбрасывает `Doctrine\DBAL\Exception\DeadlockException`; Application handler может безопасно повторить всю транзакцию целиком 1–3 раза.
- `LockWaitTimeoutException` (код 1205) — превышен `innodb_lock_wait_timeout`; транзакцию нужно повторить или вернуть 503/409, но не глушить.
- Чтобы снизить вероятность deadlock: обновлять строки в одном и том же порядке (по `id`), держать транзакции короткими, не делать `SELECT ... FOR UPDATE` по диапазонам без необходимости.
- Атомарный upsert — `INSERT ... ON DUPLICATE KEY UPDATE` (нативный SQL в Infrastructure); `ON CONFLICT` в MySQL не существует.

---

## 8. Optimistic locking (целевое)

Для редактирования из админки несколькими редакторами одновременно — `#[ORM\Version]` поле `version` на `Page`. На UI — отдавать `version` в DTO; при PUT с устаревшей версией — 409 Conflict.

```php
#[ORM\Version]
#[ORM\Column(type: 'integer')]
private int $version = 1;
```

При `UPDATE` Doctrine добавит `WHERE version = :old_version`; если 0 строк затронуто — `OptimisticLockException`, обработать в Application layer как 409.

---

## 9. Soft delete

Через trait `App\Shared\Domain\Trait\HasSoftDelete` ([код](../src/Shared/Domain/Trait/HasSoftDelete.php)):

- Поле `deleted_at` (nullable `DATETIME`).
- Метод `markDeleted(\DateTimeImmutable $at): void`.
- Запросы должны фильтровать `deletedAt IS NULL` явно (Doctrine SQL filter — целевое).

```php
$qb->where('p.deletedAt IS NULL');
```

> **Anti-pattern.** Полагаться, что «soft delete сам отфильтруется» — без Doctrine filter каждое забытое условие — это утечка удалённых записей в админку/публичную часть.

> **Целевое.** Добавить Doctrine filter `App\Shared\Infrastructure\Doctrine\SoftDeleteFilter`, включаемый по умолчанию во всех публичных контекстах и опционально отключаемый в admin (для просмотра «Корзины»).

---

## 10. Audit fields

Через trait `App\Shared\Domain\Trait\HasTimestamps` ([код](../src/Shared/Domain/Trait/HasTimestamps.php)) + `Shared\Infrastructure\Doctrine\TimestampListener` ([код](../src/Shared/Infrastructure/Doctrine/TimestampListener.php)):

- `created_at`, `updated_at` — автоматически через listener на `prePersist`/`preUpdate`.
- `created_by`, `updated_by` — целевое (для AuditLog).

> **Целевое.** Полноценный AuditLog модуль, фиксирующий каждое изменение Page/Redirect/Setting (action, user, timestamp, before/after JSON). См. [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md).

---

## 11. JSON

### 11.1 Когда использовать

- `PageBlock.content` — структура блока (например, `{"heading": "...", "items": [...]}`).
- `Setting.value` — типизированное значение настройки с поддержкой произвольной формы.
- Snapshot-поля ревизий (`seo_snapshot`, `blocks_snapshot`, `settings_snapshot`, `change_summary`), `Page.json_ld`, `Lead.consent_snapshot`.
- Любые поля «гибкой схемы» в admin.

### 11.2 Когда НЕ использовать

- Если поле — first-class бизнес-данные с инвариантами и связями (имя, цена, slug). Тогда — отдельная колонка/таблица.
- Если по полю требуются JOIN/ORDER/aggregation на больших объёмах.
- Если планируется FK на значение внутри JSON (MySQL не поддерживает FK на JSON-path).

### 11.3 Маппинг

```php
#[ORM\Column(type: Types::JSON)]   // MySQL: колонка типа JSON (бинарный формат), опция jsonb не используется
private array $content = [];
```

Особенности `JSON` в MySQL 8.4:

- Значение валидируется при записи (невалидный JSON → ошибка), хранится в оптимизированном бинарном формате.
- **Порядок ключей объекта не сохраняется**: MySQL нормализует объект (сортирует ключи по длине, затем по значению) и удаляет дубликаты ключей. Порядок элементов **массивов** сохраняется. Если порядок важен — хранить его как массив (`[{"key": ...}]`), а не как объект.
- Колонка `JSON` не может иметь литерального `DEFAULT` (только выражение `DEFAULT (JSON_OBJECT())`), поэтому пустое значение задаёт приложение (`private array $content = []`, `'{}'` в seed-миграциях).
- `JSON` нельзя проиндексировать напрямую (см. §11.5).
- Обновлять часть документа можно `JSON_SET` / `JSON_REPLACE` / `JSON_REMOVE` — MySQL умеет обновлять JSON «на месте» без переписывания всего значения, но в приложении мы обычно перезаписываем всё поле целиком.

### 11.4 Запросы

DQL/QueryBuilder не знают JSON-функций MySQL. Запросы по содержимому JSON выполняются **нативным SQL через DBAL внутри Infrastructure-слоя** (repository):

```php
// Поиск по значению ключа (оператор ->> = JSON_UNQUOTE(JSON_EXTRACT(...))):
$rows = $connection->fetchAllAssociative(
    "SELECT HEX(id) AS id FROM content_page_blocks WHERE content->>'$.heading' = :heading",
    ['heading' => 'Hello'],
);

// Существование ключа:
$sql = "SELECT HEX(id) AS id FROM content_page_blocks WHERE JSON_CONTAINS_PATH(content, 'one', '$.heading')";

// Containment (аналог jsonb @>): JSON_CONTAINS(target, candidate[, path])
$rows = $connection->fetchAllAssociative(
    'SELECT HEX(id) AS id FROM settings WHERE JSON_CONTAINS(setting_value, :candidate)',
    ['candidate' => json_encode(['type' => 'hero'], \JSON_THROW_ON_ERROR)],
);

// Значение среди элементов JSON-массива:
$sql = "SELECT HEX(id) AS id FROM content_page_blocks WHERE 'hero' MEMBER OF (content->'$.tags')";
```

Параметры, содержащие JSON, всегда передаются через bind-параметры, а не конкатенацией строк.

### 11.5 Индексы по JSON

Для production-фильтров по JSON-полям обязательно индексируемое выражение: напрямую `JSON`-колонку индексировать нельзя, поэтому используются:

1. **Индекс по generated column** (рекомендуемый вариант, виден в `EXPLAIN` как обычный индекс):

```sql
-- В миграции (два шага; оба выполняются online):
ALTER TABLE content_page_blocks
    ADD COLUMN heading_idx VARCHAR(255)
        GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(content, '$.heading'))) VIRTUAL,
    ALGORITHM=INSTANT;

ALTER TABLE content_page_blocks
    ADD INDEX idx_content_page_blocks_heading (heading_idx),
    ALGORITHM=INPLACE, LOCK=NONE;
```

2. **Functional key part** (MySQL 8.0.13+) — индекс по выражению без отдельной колонки; выражение в запросе должно совпадать с индексом дословно, включая `COLLATE`:

```sql
CREATE INDEX idx_content_page_blocks_heading_fn
    ON content_page_blocks ((CAST(content->>'$.heading' AS CHAR(255)) COLLATE utf8mb4_0900_ai_ci))
    ALGORITHM=INPLACE LOCK=NONE;
```

3. **Multi-valued index** — для поиска по значениям внутри JSON-массива (`MEMBER OF`, `JSON_CONTAINS`, `JSON_OVERLAPS`):

```sql
CREATE INDEX idx_content_page_blocks_tags
    ON content_page_blocks ((CAST(content->'$.tags' AS CHAR(64) ARRAY)));
```

> **Важно.** Doctrine ничего не знает о generated columns и functional indexes, поэтому `doctrine:migrations:diff` может предлагать их удалить. Такие объекты создаются только вручную в миграции, а лишние `DROP` из автоматического diff нужно вычищать (см. [18-migrations](18-migrations.md) §3). Если generated column нужна приложению, её можно замапить как read-only: `#[ORM\Column(columnDefinition: '...', insertable: false, updatable: false)]`.

> **Запрещено.** Делать `WHERE CAST(content AS CHAR) LIKE '%...%'` или `JSON_SEARCH` без индекса на больших таблицах — full table scan + преобразование каждого документа, сжигает CPU.

---

## 12. Indexes

| Тип | Когда |
|---|---|
| **B-tree** (default, InnoDB) | большинство FK, sort, поиск по равенству, range queries |
| **Unique по generated column** | уникальность среди живых записей (аналог partial unique, см. §13) |
| **Functional / generated column** | поиск по значению внутри JSON, нормализованные выражения (§11.5) |
| **Multi-valued** | поиск по элементам JSON-массива (§11.5) |
| **FULLTEXT** (InnoDB, `WITH PARSER ngram`) | полнотекстовый поиск по каталогу (§15, целевое) |
| **SPATIAL** | геопространственные данные (целевое) |
| **Composite** (col_a, col_b) | если запросы фильтруют по обоим в одном WHERE; работает правило левого префикса |
| **Covering** | если query можно полностью покрыть индексом: в MySQL нет `INCLUDE`, нужные колонки добавляются в сам ключ; PK неявно входит в каждый secondary-индекс, поэтому `SELECT id` по такому индексу — «Using index» |

Дополнительно:

- **Descending-индексы** (`INDEX (published_at DESC)`) в MySQL 8 реально хранятся в обратном порядке.
- **Invisible indexes** — перед удалением подозрительно неиспользуемого индекса сначала сделать его невидимым для оптимизатора и понаблюдать: `ALTER TABLE t ALTER INDEX idx_x INVISIBLE;` (откат — `VISIBLE`, мгновенно).
- **Prefix-индексы** (`INDEX (target_path(191))`) — для длинных строк, не помещающихся в лимит 3072 байта (см. §2.3).
- Индексы на FK-колонки InnoDB создаёт автоматически, если подходящего индекса нет (имена вида `IDX_8FD9B4B3727ACA70` — Doctrine их генерирует сам).

### 12.1 Когда добавлять индекс

- Запрос входит в топ-20 по суммарному времени в `performance_schema.events_statements_summary_by_digest` (или `sys.statement_analysis`) либо регулярно попадает в slow query log.
- `EXPLAIN` показывает `type: ALL` (full table scan) на > 10 000 строк с фильтром.
- Новая фича добавляет hot path по существующей колонке без индекса.

### 12.2 Когда индекс — лишний

- На колонке с очень малой кардинальностью (`is_active` boolean), если это не часть композитного индекса — оптимизатор всё равно предпочтёт full scan.
- На колонке, по которой не делаются запросы (только select).
- Дублирующий другой индекс (например, `idx_a` существует, добавляют `idx_a_b` который начинается с `a` — для запросов по `a` лишний). Для проверки: `SELECT * FROM sys.schema_redundant_indexes;` и `sys.schema_unused_indexes`.
- На временной/архивной таблице с редким доступом.

В MySQL нет partial indexes: «индекс только для живых записей» реализуется композитным индексом с `deleted_at` в ключе либо generated column (§13).

### 12.3 Композитный индекс — порядок колонок

- Сначала колонки, сравниваемые на равенство (`=`), затем колонка с range-условием или сортировкой; среди равенств — колонка с большей selectivity (более точный фильтр) впереди.
- Потом менее selective.
- Затем колонки для sort/cover.
- Пример: запрос `WHERE status = 'published' AND author_id = X ORDER BY published_at DESC` → индекс `(author_id, status, published_at DESC)`.
- Индекс используется только по левому префиксу: `(author_id, status, published_at)` обслуживает `WHERE author_id = ?` и `WHERE author_id = ? AND status = ?`, но не `WHERE status = ?`.

### 12.4 Добавление индекса

- На production — **всегда** через миграцию.
- На больших таблицах — online DDL с явным `ALGORITHM=INPLACE, LOCK=NONE` (`CREATE INDEX` в InnoDB не блокирует чтение/запись; аналог `CREATE INDEX CONCURRENTLY`). Подробности — [18-migrations](18-migrations.md) §5.7.
- Явные `ALGORITHM`/`LOCK` — обязательны: если операцию нельзя выполнить с заданными параметрами, MySQL вернёт ошибку **до** начала работы, а не молча заблокирует таблицу.
- Не через изменение Entity-attribute (Doctrine attributes не выражают `ALGORITHM`/`LOCK`, prefix/functional-индексы).

---

## 13. Constraints

- FK всегда с `ON DELETE` policy (`SET NULL` / `CASCADE` / `RESTRICT`).
- `NOT NULL` по умолчанию; nullable — только если бизнес-смысл допускает.
- `CHECK` constraints — допустимы для простых правил (например, `position >= 0`); в MySQL 8.0.16+ они реально проверяются (в более старых версиях игнорировались).
- `UNIQUE` — через индекс, явное имя `uniq_*`. NULL-значения в unique-индексе MySQL не конфликтуют между собой.
- Уникальность среди живых записей (`deleted_at IS NULL`) — **partial unique index в MySQL нет**. Эквивалент — unique-индекс по generated column, которая равна `path` для живых записей и `NULL` для удалённых:

```sql
ALTER TABLE content_pages
    ADD COLUMN path_active VARCHAR(512)
        GENERATED ALWAYS AS (IF(deleted_at IS NULL, path, NULL)) VIRTUAL,
    ALGORITHM=INSTANT;

ALTER TABLE content_pages
    ADD UNIQUE INDEX uniq_content_pages_path_active (path_active),
    ALGORITHM=INPLACE, LOCK=NONE;
```

> **Фактическое состояние.** Индекс `uniq_content_pages_path_active` (generated column `path_active`) создан в начальной миграции `Version20261004000100`. Приложение дополнительно проверяет уникальность через `PageRepositoryInterface::existsByPath()`, чтобы вернуть понятную ошибку. Generated column не отражена в attributes, поэтому `doctrine:migrations:diff` будет предлагать удалить `path_active` и индекс — такие изменения из diff нужно удалять вручную (см. §11.5). В тестах схема строится `SchemaTool` из attributes и этого индекса не содержит.

```php
#[ORM\Column(type: 'integer')]
#[Assert\PositiveOrZero]               // валидация в Domain/Application
private int $position;
// + миграция добавляет CHECK (position >= 0) на уровне БД:
// ALTER TABLE content_page_blocks ADD CONSTRAINT chk_content_page_blocks_position CHECK (position >= 0)
```

> Двойная защита (Validator + CHECK) — не избыточность, а защита от багов в ETL/импорте, которые обходят Validator.

---

## 14. Slugs и paths

- `slug` → `string(180)`, валидация в Entity (`^[a-z0-9]+(?:-[a-z0-9]+)*$`).
- `path` → `string(512)`, начинается с `/`, валидация в Entity (см. [26-seo-architecture](26-seo-architecture.md) §2).
- Уникальность `path` среди живых страниц — unique-индекс по generated column (см. §13); сравнение регистронезависимое (collation `_ci`, см. §2.2).

---

## 15. Full-text search (целевое)

Для каталога — встроенный InnoDB `FULLTEXT`. Стеммера для русского в MySQL нет, поэтому для кириллицы используется парсер `ngram` (токенизация по подстрокам длиной `ngram_token_size`, по умолчанию 2; параметр задаётся при старте сервера в `my.cnf`, а после изменения индексы нужно пересоздать):

```sql
ALTER TABLE catalog_products
    ADD FULLTEXT INDEX ft_catalog_products_search (name, summary, description) WITH PARSER ngram;
```

Запрос:

```sql
SELECT id, name,
       MATCH (name, summary, description) AGAINST ('забор профнастил' IN NATURAL LANGUAGE MODE) AS score
FROM catalog_products
WHERE MATCH (name, summary, description) AGAINST ('забор профнастил' IN NATURAL LANGUAGE MODE)
ORDER BY score DESC;
```

Особенности:

- Индекс обновляется самим InnoDB, триггеры и отдельные `tsvector`-колонки не нужны.
- Добавление первого `FULLTEXT`-индекса на таблицу перестраивает её (скрытая колонка `FTS_DOC_ID`) и допускает только `LOCK=SHARED` (запись блокируется) — на больших таблицах выполнять в окно обслуживания или через `gh-ost` / `pt-online-schema-change`.
- Для небольших таблиц и простых случаев достаточно `LIKE 'забор%'` (префиксный поиск использует индекс; `LIKE '%забор%'` — нет). Регистр не учитывается благодаря collation `_ci`.
- Ранжирование ngram хуже, чем у выделенных поисковых движков: нет морфологии, стоп-слов и синонимов.

> **Альтернатива.** Outsourcing поиска в Meilisearch/Typesense через Messenger handler (целевое для каталога > 100 K товаров или при требовании к морфологии русского).

---

## 16. N+1 и оптимизация запросов

### 16.1 Симптомы N+1

- Открытие одной страницы триггерит десятки/сотни SQL-запросов.
- В `performance_schema.events_statements_summary_by_digest` — много мелких `SELECT … FROM x WHERE id = …` подряд (высокий `COUNT_STAR`).
- В Symfony Profiler (dev) — > 30 запросов на простую страницу.

### 16.2 Решение

```php
// Плохо: N+1 при чтении $page->getBlocks() в Twig
$qb = $this->createQueryBuilder('p')
    ->where('p.path = :path')
    ->setParameter('path', $path);

// Хорошо: eager fetch блоков
$qb = $this->createQueryBuilder('p')
    ->leftJoin('p.blocks', 'b')->addSelect('b')
    ->where('p.path = :path AND p.deletedAt IS NULL AND p.status = :st')
    ->setParameter('path', $path)
    ->setParameter('st', PageStatus::Published);
```

### 16.3 Профилирование

```bash
# Локально
php bin/console doctrine:query:dql "SELECT p FROM App\Module\Content\Domain\Entity\Page p WHERE p.path = '/about'" --hydrate=array

# На staging/prod — performance_schema; пароль не передавать в argv, использовать defaults-extra-file (топ запросов по суммарному времени; таймеры в пикосекундах)
mysql --defaults-extra-file=<файл с доступом> zaborprofil -e "
SELECT COUNT_STAR AS calls,
       ROUND(SUM_TIMER_WAIT / 1e9, 1) AS total_ms,
       ROUND(AVG_TIMER_WAIT / 1e9, 2) AS avg_ms,
       DIGEST_TEXT
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'zaborprofil'
ORDER BY SUM_TIMER_WAIT DESC LIMIT 20;"

# То же в готовом виде
mysql --defaults-extra-file=<файл с доступом> -e "SELECT * FROM sys.statement_analysis LIMIT 20;"

# EXPLAIN (ANALYZE реально выполняет запрос и показывает фактическое время и число строк)
mysql --defaults-extra-file=<файл с доступом> zaborprofil -e "EXPLAIN ANALYZE SELECT * FROM content_pages WHERE path = '/about'\G"

# Интерактивный mysql-клиент в локальном Docker
make db
```

### 16.4 Anti-patterns

- Lazy-loaded коллекции, читаемые в Twig в цикле.
- `findAll()` без пагинации.
- `getResult()` для сотен тысяч строк (память).
- `count()` через `count($repo->findAll())` — должно быть `$repo->count(...)`.
- Загрузка всей сущности, когда нужен только id или один scalar (использовать `getScalarResult()` или partial DTO).

---

## 17. Что нельзя делать с БД

- Менять миграции, которые уже применены на production.
- Удалять поля без staged migration (см. [18-migrations](18-migrations.md)).
- Хранить секреты в БД без шифрования.
- Хранить большие файлы в БД (`VARBINARY`/`BLOB` для килобайтов — ок; для медиа — file storage, см. [25-files-and-uploads](25-files-and-uploads.md)).
- Делать `findAll()` в production-коде без пагинации.
- Смешивать read/write модели без причины.
- Запускать `ALTER TABLE` напрямую на production без миграции (и без явных `ALGORITHM`/`LOCK`).
- Запускать `doctrine:schema:update --force`.
- Создавать Doctrine Repository с custom публичными методами без `*RepositoryInterface` в Domain.
- Возвращать `EntityRepository` или `QueryBuilder` из Domain/Application.

---

## 18. Доступы

| Окружение | Пользователь | Привилегии |
|---|---|---|
| Local Docker | `zaborprofil` / `zaborprofil` | `ALL` на свою БД (`zaborprofil`); root доступен только внутри контейнера `mysql` |
| CI | `zaborprofil_test` / `zaborprofil_test` | `ALL` на тестовую БД |
| Staging (Beget) | пользователь БД хостинга | права выдаёт хостинг; доступ к БД ограничен списком разрешённых хостов |
| Prod | отдельный пользователь | минимально достаточный (`SELECT, INSERT, UPDATE, DELETE` + DDL-права на свою БД для миграций, без `SUPER`, `FILE`, `PROCESS`, `ALL ON *.*`) |

Пример минимальных прав (production, VPS):

```sql
CREATE USER 'zaborprofil'@'127.0.0.1' IDENTIFIED BY '<пароль из secrets>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON `zaborprofil`.* TO 'zaborprofil'@'127.0.0.1';
```

`mysqldump` запускается с `--no-tablespaces`, поэтому привилегия `PROCESS` не нужна; для консистентного дампа достаточно `SELECT`, `SHOW VIEW`, `TRIGGER` (при `--single-transaction` `LOCK TABLES` не требуется), а для `--routines` в MySQL 8.4 — `SHOW_ROUTINE`.

MySQL не должен слушать публичный интерфейс (`bind-address = 127.0.0.1`), порт `3306` закрыт firewall'ом; в локальном Docker порт публикуется на хост только через `docker-compose.override.yml` (`MYSQL_PORT`, по умолчанию `13306`), в staging/production наружу не открывается. См. [34-deployment](34-deployment.md) и [adr/0002-mysql-as-main-database](adr/0002-mysql-as-main-database.md).

---

## 19. Connection pool

- Doctrine открывает по одному соединению на PHP-FPM worker.
- Размер пула: `pm.max_children` PHP-FPM × количество messenger workers ≤ `max_connections` MySQL × 0.7 (запас на `mysqldump`, ручные подключения, резерв для администратора).
- Для production-нагрузки > 1k req/s — рассмотреть ProxySQL (целевое); PgBouncer больше не актуален.
- Долгоживущие процессы (`messenger:consume`) должны перезапускаться по `--time-limit` / `--memory-limit`, иначе соединение, закрытое сервером по `wait_timeout`, приведёт к ошибке `MySQL server has gone away` (2006).

| Параметр | Где | Рекомендация |
|---|---|---|
| `pm.max_children` (PHP-FPM) | `/etc/php/8.5/fpm/pool.d/www.conf` | `RAM / memory_limit` |
| `max_connections` (MySQL) | `my.cnf` | ≥ FPM children + worker count + 20 (по умолчанию 151) |
| `innodb_lock_wait_timeout` | `my.cnf` или per session | `10–15s` для веба (по умолчанию 50 s): ожидание row-lock, ошибка 1205 |
| `lock_wait_timeout` | per session | ожидание metadata lock (MDL) для DDL, по умолчанию 1 год; в миграциях `SET SESSION lock_wait_timeout = 5`, иначе `ALTER TABLE` будет ждать MDL и блокировать все новые запросы к таблице (см. [18-migrations](18-migrations.md) §5.0) |
| `wait_timeout` / `interactive_timeout` | `my.cnf` | по умолчанию 28800 s; аналога `idle_in_transaction_session_timeout` в MySQL нет — зависшие транзакции ловим мониторингом (`information_schema.INNODB_TRX`) |
| `max_execution_time` | `my.cnf`, per session или optimizer hint `/*+ MAX_EXECUTION_TIME(30000) */` | `30000` (мс) для веба; ограничивает только `SELECT`; для миграций `0` |
| `innodb_buffer_pool_size` | `my.cnf` | 50–70% RAM на выделенном сервере БД |
| `transaction_isolation` | `my.cnf` | `REPEATABLE READ` (по умолчанию); менять только осознанно |

---

## 20. Чек-лист добавления новой таблицы

- [ ] Entity с инвариантами в Domain.
- [ ] Repository interface в Domain (`App\Module\X\Domain\Repository\YRepositoryInterface`).
- [ ] Doctrine Repository implements interface в Infrastructure (`App\Module\X\Infrastructure\Repository\DoctrineYRepository`).
- [ ] Миграция `up()` + `down()` (см. [18-migrations](18-migrations.md)); помнить, что DDL в MySQL не транзакционен.
- [ ] Имя таблицы и индексов — явные, по конвенции (§3.1).
- [ ] FK с `ON DELETE`.
- [ ] Timestamps (через trait, если бизнес требует).
- [ ] Soft delete (если предметная область требует).
- [ ] Тесты: unit на Entity (инварианты), integration на repository.
- [ ] `php bin/console doctrine:schema:validate --skip-sync` — без ошибок.
- [ ] Документация модели в [05-domain-model](05-domain-model.md) обновлена.
- [ ] Если новая таблица будет расти — добавлены индексы под ожидаемые запросы.

---

## 21. Чек-лист добавления нового поля

- [ ] Поле — действительно first-class данные, а не JSON-кандидат.
- [ ] Тип MySQL согласован (`VARCHAR(N)` vs `LONGTEXT`, `DECIMAL` vs `DOUBLE`, `DATETIME` vs `TIMESTAMP`; Doctrine `datetime_immutable` → `DATETIME`, `boolean` → `TINYINT`), учтён лимит ключа индекса 3072 байта и 4 байта на символ utf8mb4.
- [ ] Default / NOT NULL согласовано.
- [ ] Migration backward-compatible (см. [18-migrations](18-migrations.md) §5).
- [ ] Entity attribute согласован с миграцией.
- [ ] Repository обновлён (если тип запросов изменился).
- [ ] Validator constraints добавлены (Domain — инварианты, Application DTO — формат входа).
- [ ] Индекс добавлен, если поле будет в WHERE или ORDER BY.
- [ ] Тесты на Entity и Repository.
- [ ] Документация модели обновлена.

---

## 22. Performance review checklist

Перед мерджем фичи, добавляющей запросы к БД:

- [ ] `EXPLAIN ANALYZE` (или `EXPLAIN FORMAT=TREE`) для главного query фичи приложен к PR.
- [ ] План запроса использует индекс (`type`: `const`/`eq_ref`/`ref`/`range`, при покрытии — `Using index`), а не `type: ALL` (full table scan) на больших таблицах; нет `Using filesort`/`Using temporary` на hot path без причины.
- [ ] Нет N+1 в Twig (проверить количество queries в Symfony Profiler на ключевой странице).
- [ ] Если запрос > 100 мс на dev-данных — оптимизировать или добавить индекс.
- [ ] Кэширование рассмотрено (см. [23-cache](23-cache.md)).
- [ ] Pagination для всех листингов.
- [ ] Eager fetch для связей, читаемых в шаблоне.

---

## 23. Чек-лист для AI-агента при работе с БД

Перед изменением:

- [ ] Прочитан этот документ + [18-migrations](18-migrations.md) + [05-domain-model](05-domain-model.md).
- [ ] Классифицирована задача (см. [41-implementation-playbook](41-implementation-playbook.md) §12 / §13).
- [ ] Определено: только маппинг или нужна миграция.

Во время изменения:

- [ ] Repository interface — в Domain, реализация — в Infrastructure.
- [ ] Имена индексов / FK — явные.
- [ ] `findAll()` без пагинации не добавляется.
- [ ] Не возвращается `QueryBuilder` за пределы Infrastructure.
- [ ] Индекс по JSON-выражению (generated column / functional / multi-valued, §11.5) добавлен, если будут запросы по содержимому JSON.
- [ ] Транзакция — на уровне Application handler.

После изменения:

- [ ] `doctrine:schema:validate --skip-sync` — без ошибок.
- [ ] Repository тесты — зелёные.
- [ ] Performance review checklist пройден.
- [ ] Документация обновлена.

Запрещено:

- Импорт `EntityManagerInterface` в контроллере.
- Возврат Doctrine entity напрямую в JSON API.
- Прямой SQL в контроллере / Twig.
- Изменение применённой миграции.

---

## 24. Связанные документы

- [05-domain-model](05-domain-model.md) — что хранится в БД.
- [10-domain-layer](10-domain-layer.md) — Entity, Value Object, Repository interface.
- [11-infrastructure-layer](11-infrastructure-layer.md) — Doctrine Repository, listeners.
- [04-layer-rules](04-layer-rules.md) — что Domain не знает про Doctrine.
- [18-migrations](18-migrations.md) — как менять схему.
- [23-cache](23-cache.md) — кэш над запросами.
- [37-runbooks](37-runbooks.md) — инциденты MySQL (11–15, 50).
- [adr/0002-mysql-as-main-database](adr/0002-mysql-as-main-database.md)
- [adr/0004-doctrine-orm-usage](adr/0004-doctrine-orm-usage.md)
