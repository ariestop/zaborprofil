# ADR-0011: ULID как первичные ключи доменных сущностей

## Статус

Accepted, 2026.

## Контекст

В CMS-проекте `zaborprofil` появляется большое число доменных сущностей: `Page`, `PageBlock`, `Setting`, `Redirect`, `AdminUser`, плюс будущие модули (`Lead`, `Product`, `Order`, `Customer`, `MediaItem`, `Menu`).

Для каждой сущности нужно выбрать стратегию первичного ключа. Варианты:

1. `BIGINT AUTO_INCREMENT` (auto-increment integer).
2. UUID v4 (random 128-bit).
3. UUID v7 (time-ordered 128-bit, RFC 9562).
4. ULID (time-ordered 128-bit, base32-кодированный, 26-символьная строка).
5. Composite natural keys (`slug`, `path`).

Требования:

- идентификаторы фигурируют в admin UI и в админ-API → должны быть читаемы и копируемы;
- идентификаторы потенциально попадают в публичные URL (целевое: для Order, Product) → не должны раскрывать порядок или количество записей;
- БД — MySQL 8.4 (InnoDB), миллионы строк в перспективе (каталог + лиды) → последовательный insert критичен: первичный ключ InnoDB — это clustered index, а PK входит в каждый secondary-индекс;
- Doctrine ORM 3 → нужна нативная поддержка типа в DBAL;
- проект — модульный монолит, но в будущем возможно выделение микросервисов → ID должны быть глобально уникальны без координации.

## Проблема

`AUTO_INCREMENT` раскрывает порядок записей и не подходит для публичных URL (security through obscurity-аргумент: видя `/order/12345`, можно предположить `/order/12344`, оценить общее число заказов и т. п.).

UUID v4 решает раскрытие, но:

- random insert «разбрасывает» строки по clustered index InnoDB → page split'ы, фрагментация и больше I/O при insert/lookup;
- занимает 16 байт в бинарном виде, но 36 символов (до 144 байт в utf8mb4) в строковом `CHAR(36)`, который часто используют по умолчанию;
- не сортируется по времени создания.

UUID v7 / ULID решают обе проблемы: скрытие порядка + последовательный insert.

## Решение

Использовать **ULID** как первичный ключ всех доменных сущностей через `Symfony\Component\Uid\Ulid` и Doctrine тип `'ulid'`.

Хранить как `BINARY(16)`. Платформа MySQL не имеет нативного GUID-типа (в PostgreSQL Doctrine использовал бы `uuid`), поэтому Doctrine-тип `ulid` (`Symfony\Bridge\Doctrine\Types\UlidType`) на MySQL автоматически использует `BINARY(16)` — 16 байт против 104 байт у `CHAR(26)` в utf8mb4. Это существенно уменьшает размер первичного и всех secondary-индексов InnoDB. В SQL-консоли значение читается через `HEX(id)`, поиск — `WHERE id = UNHEX('<hex>')` (hex — `Ulid::toHex()`); подробнее [17-doctrine-and-database](../17-doctrine-and-database.md) §4.1.

Класс Entity:

```php
use Symfony\Component\Uid\Ulid;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class Page
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $id;

    public function __construct()
    {
        $this->id = new Ulid();
    }

    public function id(): Ulid
    {
        return $this->id;
    }
}
```

Сериализация наружу — строкой (`(string) $page->id()` → `01HABCDEF...26 chars`).

## Причины

- **Sortable.** ULID лексикографически (и в бинарном виде — побайтово) сортируется по времени → последовательный insert в конец clustered index InnoDB, эффективный pagination по «новым».
- **Compact human-readable.** 26 ASCII-символов (Crockford base32). Легко копировать, не путается с UUID-строкой через дефисы.
- **No coordination.** Генерируется на стороне приложения; не нужен round-trip к БД для получения id.
- **Не раскрывает порядок.** Timestamp в ULID — миллисекундная точность, но рандомная часть скрывает локальный порядок и количество записей.
- **Symfony native.** `Symfony\Component\Uid\Ulid` входит в `symfony/uid`, уже подтянут как dependency. Doctrine тип встроен.
- **Будущее микросервисное разделение.** Глобально уникальны без центральной последовательности.
- **Совместимость с Messenger.** ID в payload сообщений, между нодами, между release’ами — без коллизий.
- **Легко переходить на UUID v7 в будущем.** Семантически почти эквивалентны; при необходимости — миграция.

## Альтернативы

| Альтернатива | Плюсы | Минусы | Почему отклонено |
|---|---|---|---|
| `BIGINT AUTO_INCREMENT` | Минимум места (8 байт), быстрый insert | Раскрывает порядок и количество, требует round-trip к БД для получения id, проблема с sharding | Не подходит для публичных URL, плохо для микросервисов |
| UUID v4 | Не раскрывает ничего | Random — фрагментирует clustered index, в 2x больше места, не сортируется | Хуже производительность insert на больших таблицах |
| UUID v7 | Time-ordered, стандарт RFC 9562 | Поддержка в Doctrine появилась недавно, меньше адопции в инструментах | Близко к ULID, но ULID более компактен в строковом представлении (26 vs 36 chars) |
| ULID + публичный slug | Слаг для URL, ULID для БД | Двойной идентификатор, сложнее API | Целевое для контента (Page имеет path), но для Order/Lead — избыточно |
| Composite natural keys | Семантичны | Невозможно изменить natural key, требует JOIN на каждое использование | Подходит только для таблиц-словарей |

## Компромиссы

- ULID занимает 16 байт против 8 байт у `BIGINT AUTO_INCREMENT` (и 16 байт копируются в каждый secondary-индекс InnoDB) — приемлемо для текущего масштаба (миллионы, не миллиарды строк).
- Сравнение по `id` — binary (`BINARY(16)`), медленнее int comparison на ~10–20% — приемлемо.
- ULID кодируется base32 без дефисов — выглядит непривычно для разработчиков из мира UUID; компенсируется документацией и tooling.
- Symfony `Ulid::generate()` использует random для последних 80 бит — есть микроскопический риск коллизии в одной миллисекунде в одном процессе при > 10^10 генераций (нерелевантно).

## Риски

| Риск | Вероятность | Импакт | Mitigation |
|---|---|---|---|
| Doctrine `ulid` тип меняет storage между мажорами | Низкая | Средний | Зафиксировать версию `doctrine/dbal` в `composer.lock`, мониторить changelog |
| Нагрузка делает binary comparison узким местом | Очень низкая | Низкий | На текущем масштабе нерелевантно; при росте — переход на UUID v7 (в MySQL также `BINARY(16)`, `UUID_TO_BIN`/`BIN_TO_UUID` на стороне SQL) |
| Команда путает ULID с UUID, либо пытается читать `BINARY(16)` в консоли как строку | Средняя | Низкий | Документация + linter правило (целевое); в DTO использовать `Symfony\Component\Uid\Ulid::fromString()` |
| ID попадает в логи / Telegram alerts с временем создания | Низкая | Низкий | Это допустимо: ULID не критично-секретен; важен только тот факт, что не раскрывает порядок в админке |
| Внешние интеграции не понимают base32 ULID | Низкая | Средний | Всегда отдавать как opaque string; для интеграций, требующих UUID — генерировать UUID v7 в отдельном поле (целевое) |

## Последствия

- Все Entity получают `private Ulid $id` через конструктор (`new Ulid()`).
- Repository `getById(Ulid $id)` принимает `Ulid` объект.
- DTO принимают строку, валидируют через `Ulid::isValid($s)`, конвертируют через `Ulid::fromString($s)`.
- В URL и в JSON API id выглядит как `01HABCDEF...` (26 chars).
- В Twig — `{{ page.id }}` (`Ulid::__toString()`).
- В миграциях — колонка `BINARY(16)` (Doctrine type `ulid`); в seed-миграциях значения передаются как `Ulid::fromString(...)->toBinary()` с `ParameterType::BINARY`; внешние ключи на ULID — тоже `BINARY(16)` (типы и длина должны совпадать).
- В тестах — фикстуры используют либо `new Ulid()`, либо фиксированные ULID для воспроизводимости (`Ulid::fromString('01HXXX...')`).

## Когда пересмотреть решение

- Появляется внешняя интеграция, требующая обязательного UUID v4/v7 в id, и поддерживать дополнительный uuid-столбец дороже, чем перейти на UUID.
- MySQL добавляет нативный `ulid`/GUID тип (сейчас нет: есть только функции `UUID_TO_BIN`/`BIN_TO_UUID` для UUID и `BINARY(16)`).
- Появляется требование обратимого кодирования ID в URL (slug/UUID-mappings, hashids) — пересмотреть в сторону отдельного публичного идентификатора.
- Нагрузка превышает миллиарды записей в одной таблице, и место в индексе становится узким местом.

## Связанные документы

- [05-domain-model](../05-domain-model.md)
- [10-domain-layer](../10-domain-layer.md)
- [17-doctrine-and-database](../17-doctrine-and-database.md) §4 — Identifiers
- [adr/0002-mysql-as-main-database](0002-mysql-as-main-database.md)
- [adr/0004-doctrine-orm-usage](0004-doctrine-orm-usage.md)
