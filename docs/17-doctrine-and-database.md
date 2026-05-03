# 17. Doctrine и база данных

> **Назначение документа.** Зафиксировать правила работы с PostgreSQL и Doctrine ORM/DBAL в проекте `zaborprofil`. Покрывает маппинг, naming, индексы, транзакции, JSONB, soft delete, audit-поля, оптимизацию запросов.
>
> **Аудитория.** Backend-разработчики, AI-агенты, DBA, тимлиды.
>
> **Связанные документы.** [18-migrations](18-migrations.md), [05-domain-model](05-domain-model.md), [10-domain-layer](10-domain-layer.md), [11-infrastructure-layer](11-infrastructure-layer.md), [04-layer-rules](04-layer-rules.md), [ADR-0002](adr/0002-postgresql-as-main-database.md), [ADR-0004](adr/0004-doctrine-orm-usage.md).

---

## 1. Конфигурация

`config/packages/doctrine.yaml`:

- DBAL: `url: '%env(resolve:DATABASE_URL)%'`, `server_version: '18'`.
- ORM: `naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware`, `validate_xml_mapping: true`.
- Маппинг: `type: attribute`, `dir: %kernel.project_dir%/src`, `prefix: App`, `alias: App`.
- В `prod`: query/result cache pools на Redis (`doctrine.system_cache_pool`, `doctrine.result_cache_pool`).

> **Фактическое состояние.** В проекте используется Doctrine ORM 3, DBAL 4, doctrine-bundle 3.x (см. [composer.json](../composer.json)). Маппинг — только attributes. Result cache на Redis включается в prod-окружении.

---

## 2. PostgreSQL: версия и расширения

| Что | Значение |
|---|---|
| Версия | `>= 18` |
| Минимальный extension set | `pdo_pgsql`, `intl`, `mbstring` |
| JSONB | используется для `PageBlock.content`/`settings`, `Setting.value` |
| ULID | через `Symfony\Component\Uid\Ulid`, тип Doctrine `'ulid'` |
| Partial unique index | поддерживается; используется для `content_pages.path WHERE deleted_at IS NULL` |
| Желательные расширения (целевое) | `pg_stat_statements` (анализ slow queries), `pg_trgm` (FTS / similarity) |

> **Локально.** `pdo_pgsql` обязательно включён в PHP-установке (см. [AGENTS.md](../AGENTS.md)).

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
| Первичный ключ | `id` | `id ULID PRIMARY KEY` |
| Foreign key column | `<entity>_id` | `page_id`, `author_id` |
| FK constraint | `fk_<table>_<column>` | `fk_content_page_blocks_page_id` |
| Обычный индекс | `idx_<table>_<columns>` | `idx_content_pages_status` |
| Уникальный индекс | `uniq_<table>_<columns>` | `uniq_content_pages_path_active` |
| Partial unique | `uniq_<table>_<columns>_<predicate>` | `uniq_content_pages_path_active` (`WHERE deleted_at IS NULL`) |
| GIN-индекс | `gin_<table>_<column>` | `gin_settings_value` |
| Boolean | `is_<x>` или `has_<x>` | `is_active`, `has_blocks` |
| Timestamps | `created_at`, `updated_at`, `deleted_at`, `published_at` | — |

> **Запрещено.** Имена таблиц/колонок на кириллице, CamelCase в БД, неконсистентные суффиксы (`_dt` vs `_at`), сокращения без причины (`pg` вместо `page`).

---

## 4. Identifiers

Используется ULID:

- 26-символьная сортируемая строка (`01HABCDEF...`).
- Сортируется по времени, как `bigserial`, без блокировок счётчика.
- Лучше для индекса B-tree, чем UUID v4 (последовательный insert вместо random).
- Безопаснее для публичных URL, чем `bigint` (не раскрывает порядок и количество записей).
- Тип Doctrine: `'ulid'` (`Symfony\Component\Uid\Ulid`).

```php
#[ORM\Id]
#[ORM\Column(type: 'ulid', unique: true)]
private Ulid $id;

public function __construct()
{
    $this->id = new Ulid();
}
```

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

> **Anti-pattern.** Имена через автогенерацию Doctrine (`page_block_id_seq`, `IDX_AB12CD34`). Они нечитаемы при инциденте и при просмотре `pg_stat_activity`.

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
- Долгие транзакции (минуты) — блокируют autovacuum и другие запросы.
- Внешний HTTP-вызов внутри транзакции — таймаут блокирует БД.
- `flush()` в цикле — N round-trips к БД. Использовать batch flush (`flush + clear` каждые 100 entities).

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

- Поле `deleted_at` (nullable timestamp).
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

## 11. JSONB

### 11.1 Когда использовать

- `PageBlock.content` — структура блока (например, `{"heading": "...", "items": [...]}`).
- `Setting.value` — типизированное значение настройки с поддержкой произвольной формы.
- Любые поля «гибкой схемы» в admin.

### 11.2 Когда НЕ использовать

- Если поле — first-class бизнес-данные с инвариантами и связями (имя, цена, slug). Тогда — отдельная колонка/таблица.
- Если по полю требуются JOIN/ORDER/aggregation на больших объёмах.
- Если планируется FK на значение в JSONB.

### 11.3 Маппинг

```php
#[ORM\Column(type: Types::JSON)]   // PostgreSQL автоматически кладёт в jsonb
private array $content = [];
```

### 11.4 Запросы

```php
// Поиск по jsonb-ключу:
$qb->where("p.content ->> 'heading' = :heading")
   ->setParameter('heading', 'Hello');

// Существование ключа:
$qb->where("p.content ? 'heading'");

// Containment (jsonb @> jsonb):
$qb->where("p.content @> :search")
   ->setParameter('search', json_encode(['type' => 'hero']));
```

### 11.5 GIN-индексы

Для production-фильтров по JSONB-полям обязателен GIN-индекс:

```sql
-- В миграции:
CREATE INDEX gin_settings_value ON settings USING GIN (value);

-- Для конкретного path:
CREATE INDEX gin_pages_content_heading ON pages USING GIN ((content -> 'heading'));
```

`GIN` ускоряет операторы `?`, `?&`, `?|`, `@>`. Без него такие запросы делают seq scan.

> **Запрещено.** Делать `WHERE content::text LIKE '%...%'` на больших таблицах — seq scan + cast, сжигает CPU.

---

## 12. Indexes

| Тип | Когда |
|---|---|
| **B-tree** (default) | большинство FK, sort, поиск по равенству, range queries |
| **Partial unique** | уникальность среди живых записей (`WHERE deleted_at IS NULL`) |
| **GIN** | поиск по JSONB, массивам, FTS (`tsvector`) |
| **GIST** | геопространственные данные, range types (целевое) |
| **Composite** (col_a, col_b) | если запросы фильтруют по обоим в одном WHERE |
| **Covering** (`INCLUDE`) | если query selects можно полностью покрыть индексом |

### 12.1 Когда добавлять индекс

- Запрос в `pg_stat_statements` входит в топ-20 по `total_exec_time`.
- `EXPLAIN (ANALYZE, BUFFERS)` показывает Seq Scan на > 10 000 строк с фильтром.
- Новая фича добавляет hot path по существующей колонке без индекса.

### 12.2 Когда индекс — лишний

- На колонке с очень малой кардинальностью (`is_active` boolean) без partial-условия.
- На колонке, по которой не делаются запросы (только select).
- Дублирующий другой индекс (например, `idx_a` существует, добавляют `idx_a_b` который начинается с `a` — для запросов по `a` лишний).
- На временной/архивной таблице с редким доступом.

### 12.3 Композитный индекс — порядок колонок

- Сначала колонка с большей selectivity (более точный фильтр).
- Потом менее selective.
- Затем колонки для sort/cover.
- Пример: запрос `WHERE status = 'published' AND author_id = X ORDER BY published_at DESC` → индекс `(author_id, status, published_at DESC)`.

### 12.4 Добавление индекса

- На production — **всегда** через миграцию.
- На больших таблицах — `CREATE INDEX CONCURRENTLY` (см. [18-migrations](18-migrations.md) §5.7).
- Не через изменение Entity-attribute (Doctrine attributes не различают CONCURRENTLY).

---

## 13. Constraints

- FK всегда с `ON DELETE` policy (`SET NULL` / `CASCADE` / `RESTRICT`).
- `NOT NULL` по умолчанию; nullable — только если бизнес-смысл допускает.
- `CHECK` constraints — допустимы для простых правил (например, `position >= 0`).
- `UNIQUE` — через индекс, явное имя `uniq_*`.
- Partial unique: `CREATE UNIQUE INDEX uniq_content_pages_path_active ON content_pages (path) WHERE deleted_at IS NULL`.

```php
#[ORM\Column(type: 'integer')]
#[Assert\PositiveOrZero]               // валидация в Domain/Application
private int $position;
// + миграция добавляет CHECK (position >= 0) на уровне БД
```

> Двойная защита (Validator + CHECK) — не избыточность, а защита от багов в ETL/импорте, которые обходят Validator.

---

## 14. Slugs и paths

- `slug` → `string(180)`, валидация в Entity (`^[a-z0-9]+(?:-[a-z0-9]+)*$`).
- `path` → `string(512)`, начинается с `/`, валидация в Entity (см. [26-seo-architecture](26-seo-architecture.md) §2).
- Уникальность `path` — partial unique index (см. §12).

---

## 15. Full-text search (целевое)

Для каталога — PostgreSQL FTS:

```sql
ALTER TABLE products ADD COLUMN search_tsv tsvector;
UPDATE products SET search_tsv =
    to_tsvector('russian', coalesce(title,'') || ' ' || coalesce(description,''));
CREATE INDEX gin_products_search ON products USING GIN (search_tsv);

-- Trigger для автообновления:
CREATE TRIGGER products_search_tsv_update
BEFORE INSERT OR UPDATE OF title, description ON products
FOR EACH ROW EXECUTE FUNCTION
    tsvector_update_trigger(search_tsv, 'pg_catalog.russian', title, description);
```

Запрос:

```sql
SELECT * FROM products
WHERE search_tsv @@ plainto_tsquery('russian', 'забор профнастил')
ORDER BY ts_rank(search_tsv, plainto_tsquery('russian', 'забор профнастил')) DESC;
```

> **Альтернатива.** Outsourcing FTS в Meilisearch/Typesense через Messenger handler (целевое для каталога > 100 K товаров).

---

## 16. N+1 и оптимизация запросов

### 16.1 Симптомы N+1

- Открытие одной страницы триггерит десятки/сотни SQL-запросов.
- В `pg_stat_statements` — много мелких `SELECT … FROM x WHERE id = …` подряд.
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

# На staging/prod — pg_stat_statements
sudo -u postgres psql -d zaborprofil -c "
SELECT calls, total_exec_time, mean_exec_time, query
FROM pg_stat_statements
ORDER BY total_exec_time DESC LIMIT 20;"

# EXPLAIN
sudo -u postgres psql -d zaborprofil -c "EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM content_pages WHERE path = '/about';"
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
- Хранить большие файлы в БД (`bytea` для килобайтов — ок; для медиа — file storage, см. [25-files-and-uploads](25-files-and-uploads.md)).
- Делать `findAll()` в production-коде без пагинации.
- Смешивать read/write модели без причины.
- Запускать `ALTER TABLE` напрямую на production без миграции.
- Запускать `doctrine:schema:update --force`.
- Создавать Doctrine Repository с custom публичными методами без `*RepositoryInterface` в Domain.
- Возвращать `EntityRepository` или `QueryBuilder` из Domain/Application.

---

## 18. Доступы

| Окружение | Пользователь | Привилегии |
|---|---|---|
| Local Docker | `zaborprofil` / `zaborprofil` | full на свою БД |
| CI | `zaborprofil_test` / `zaborprofil_test` | full на тестовую БД |
| Staging/Prod | отдельный пользователь | минимально достаточный (CRUD на свои таблицы, без `SUPERUSER`) |

PostgreSQL не должен слушать публичный интерфейс (`listen_addresses = 'localhost'`), см. [34-deployment](34-deployment.md) и [adr/0002-postgresql-as-main-database](adr/0002-postgresql-as-main-database.md).

---

## 19. Connection pool

- Doctrine открывает по одному соединению на PHP-FPM worker.
- Размер пула: `pm.max_children` PHP-FPM × количество messenger workers ≤ `max_connections` PostgreSQL × 0.7 (запас на pg_dump, ручные подключения).
- Для production-нагрузки > 1k req/s — рассмотреть PgBouncer (целевое).

| Параметр | Где | Рекомендация |
|---|---|---|
| `pm.max_children` (PHP-FPM) | `/etc/php/8.5/fpm/pool.d/www.conf` | `RAM / memory_limit` |
| `max_connections` (PostgreSQL) | `postgresql.conf` | ≥ FPM children + worker count + 20 |
| `idle_in_transaction_session_timeout` | `postgresql.conf` | `60s` |
| `statement_timeout` | `postgresql.conf` или per session | `30s` для веб, `0` для миграций |

---

## 20. Чек-лист добавления новой таблицы

- [ ] Entity с инвариантами в Domain.
- [ ] Repository interface в Domain (`App\Module\X\Domain\Repository\YRepositoryInterface`).
- [ ] Doctrine Repository implements interface в Infrastructure (`App\Module\X\Infrastructure\Repository\DoctrineYRepository`).
- [ ] Миграция `up()` + `down()` (см. [18-migrations](18-migrations.md)).
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

- [ ] Поле — действительно first-class данные, а не JSONB-кандидат.
- [ ] Тип PostgreSQL согласован (`text` vs `varchar(N)`, `numeric` vs `float8`, `timestamp with time zone` vs `timestamp`).
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

- [ ] EXPLAIN (ANALYZE, BUFFERS) для главного query фичи приложен к PR.
- [ ] План запроса — Index Scan / Index Only Scan, не Seq Scan на больших таблицах.
- [ ] Нет N+1 в Twig (проверить количество queries в Symfony Profiler на ключевой странице).
- [ ] Если запрос > 100 мс на dev-данных — оптимизировать или добавить индекс.
- [ ] Кэширование рассмотрено (см. [23-cache-and-redis](23-cache-and-redis.md)).
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
- [ ] JSONB-индекс добавлен, если будут запросы по JSONB.
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
- [23-cache-and-redis](23-cache-and-redis.md) — кэш над запросами.
- [37-runbooks](37-runbooks.md) — инциденты PostgreSQL (11–15, 50).
- [adr/0002-postgresql-as-main-database](adr/0002-postgresql-as-main-database.md)
- [adr/0004-doctrine-orm-usage](adr/0004-doctrine-orm-usage.md)
