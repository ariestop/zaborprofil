# ADR-0004: Doctrine ORM как persistence

## Статус

Accepted, 2026.

## Контекст

Проект — реляционный CMS с переходом в e-commerce. Нужны:

- expressive mapping;
- migrations;
- transactions / unit of work;
- read-write поверх PostgreSQL;
- работа с jsonb, ulid, partial indexes (через нативный SQL в миграциях).

## Решение

Использовать **Doctrine ORM 3 + Doctrine DBAL 4 + Doctrine Migrations 4**.

- Маппинг — только PHP attributes.
- Repository — interface в Domain, реализация в Infrastructure (наследует `ServiceEntityRepository`).
- В Application/Domain — **не** использовать `EntityManagerInterface` напрямую.
- Миграции — DBAL/SQL only (не импортируют Domain).

## Причины

- **Зрелая ORM**, фактический стандарт для Symfony.
- **Attributes mapping** — без отдельных XML/YAML.
- **Поддержка PostgreSQL** на нужном уровне (jsonb через `'json'`, ulid через Symfony Uid).
- **Migrations** — простой workflow `diff` + `migrate`.
- **Unit of Work** — транзакционная семантика.

## Последствия

- Doctrine attributes на Domain entities — допустимая «протекшая» зависимость, потому что это маппинг, а не I/O.
- Domain не вызывает `flush()`. Это — задача repository (или явно отдельного `UnitOfWork` сервиса в Application).
- Lazy-loading включён по умолчанию; контролируется через `fetch=EAGER` для критичных связей и `JOIN FETCH` в запросах для предотвращения N+1.
- Doctrine может усложниться при сложных queries — допускаются raw DBAL для производительности (с тестами).

## Анти-паттерны, которые ADR пресекает

- Передача `EntityManagerInterface` в контроллер.
- Использование `EntityRepository` напрямую (всегда через interface).
- `findAll()` без пагинации.
- Вызов Domain Entity внутри миграции.
- Generic god-repository со 100 разнородных методов.

## Альтернативы

- **Cycle ORM** — мощно, но меньше комьюнити, риск миграции в новых версиях.
- **Raw PDO + DBAL только** — теряем mapping, ORM-удобство; не стоит.
- **Eloquent (Laravel ActiveRecord)** — нарушает DDD-нейтральность.

## Когда пересмотреть

- Doctrine 4 выходит и предлагает несовместимые изменения, требующие глобального переезда.
- Нужны read-replica + complex CQRS — рассмотреть DBAL для read-side.
- Производительность критична на конкретных hot-paths — допустимо снижаться до DBAL точечно.

## Связанные документы

- [17-doctrine-and-database](../17-doctrine-and-database.md)
- [18-migrations](../18-migrations.md)
- [11-infrastructure-layer](../11-infrastructure-layer.md)
