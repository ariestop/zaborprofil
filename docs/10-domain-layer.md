# 10. Domain layer

## Назначение

Domain — самый стабильный, самый важный слой. Здесь живут **правила бизнеса**.

Если завтра уйдёт Symfony, Doctrine, Redis, Twig — Domain должен пережить.

## Состав

| Папка | Что внутри |
|---|---|
| `Domain/Entity/` | Doctrine entities + бизнес-методы |
| `Domain/ValueObject/` | immutable PHP-классы (Slug, Path, Money) — целевое |
| `Domain/Enum/` | backed enums (`PageStatus`, `PageType`, `BlockType`, `AdminPermission`) |
| `Domain/Event/` | immutable доменные события (целевое) |
| `Domain/Exception/` | domain-specific exceptions |
| `Domain/Repository/` | **только interface** |
| `Domain/Service/` | чистые domain services без I/O |
| `Domain/Trait/` | shared traits (`HasTimestamps`, `HasSoftDelete` живут в `Shared`) |

## Что разрешено импортировать в Domain

- PHP stdlib (`DateTimeImmutable`, `Stringable`, `JsonSerializable`).
- `Symfony\Component\Uid\Ulid` (это identifier-утилита, не runtime-фреймворк).
- Doctrine ORM **attributes** для маппинга.
- `App\Shared\Domain\*`.
- Другие классы из `Domain/` этого модуля.

## Что **запрещено**

- `Symfony\Component\HttpFoundation\*`
- `Twig\*`
- `Predis\*` / `Redis`
- Файловая система (`fopen`, `file_*`)
- `Symfony\Component\Mailer\*`
- `Symfony\Component\Cache\*`
- `Doctrine\ORM\EntityManagerInterface` (вызовы; attributes — допустимы)
- HTTP-клиенты

## Entity

### Когда Entity = Domain Entity

Когда инварианты модели + бизнес-операции делают Entity «богатой». См. [Page](../src/Module/Content/Domain/Entity/Page.php) и [PageBlock](../src/Module/Content/Domain/Entity/PageBlock.php).

### Когда Persistence отделён от Domain

Если persistence-форма противоречит доменному поведению (например, нужна другая агрегация поля), отделяем:

- `Domain/Page` — pure PHP с поведением.
- `Infrastructure/Doctrine/Entity/PagePersistence` — Doctrine model.
- Mapper в репозитории.

В текущем проекте такая декомпозиция применена точечно — `AdminUser` лежит в `User/Infrastructure/Doctrine/Entity/`, потому что это persistence model для Symfony Security и не имеет богатого поведения.

### Правила

- Все поля `private`.
- Setter — по бизнес-смыслу: `publish()`, `archive()`, `addBlock()`, `update(...)`. Не `setStatus()` без правил.
- Инварианты — в конструкторе и в методах. Невалидное состояние **никогда** не должно сохраняться.
- Бросать `\InvalidArgumentException` или domain exception при нарушении.
- Не возвращать наружу `Doctrine\Common\Collections\Collection` (превращать в `array` через геттер).

## Value Object

Признаки VO:

- Не имеет идентичности.
- Сравнивается по значению.
- Immutable.
- Полностью валидируется в конструкторе.

Когда вводить VO:

- Поле имеет нетривиальное поведение (`Slug::isValid()`, `Path::asAbsolute()`).
- Поле повторяется в нескольких сущностях.
- Поле должно гарантировать инварианты, которые легко забыть на вызывающей стороне.

Целевые VO для проекта: `Slug`, `Path`, `EmailAddress`, `Money`, `SeoTitle`, `SeoDescription`.

## Domain Service

Domain Service — функция/класс без состояния, оперирующий несколькими сущностями.

Используется, когда:

- Поведение не принадлежит ни одной из участвующих сущностей.
- Нужно вычислить, не сохраняя.

Пример (целевое): `RedirectChainResolver` — найти финальный target для цепочки `Redirect`’ов.

Запрещено в Domain Service:

- I/O (HTTP, БД, FS).
- Зависимость от Symfony/Doctrine.

## Domain Event (целевое)

```php
final class PageWasPublished
{
    public function __construct(
        public readonly string $pageId,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
```

- Immutable.
- Только примитивы и Value Object.
- Никаких Doctrine entity внутри.
- Публикуется в `Application/Handler` и обрабатывается в Infrastructure listeners.

## Repository interface

Лежит в `Domain/Repository/`. Минимально необходимые методы:

```php
interface PageRepositoryInterface
{
    public function getById(Ulid $id): Page;            // throws ContentNotFoundException
    public function findByPath(string $path): ?Page;
    public function existsByPath(string $path): bool;
    public function save(Page $page): void;
    public function remove(Page $page): void;
}
```

Запрещено:

- Возвращать `QueryBuilder` или Doctrine `Query` наружу.
- Принимать `Criteria` от Doctrine.
- Раскрывать внутренний SQL.

## Enum

Backed PHP enums:

```php
enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
```

Помогают в `match` без if-tree и работают через Doctrine `enumType:`.

## Invariants — где проверять

| Что | Где |
|---|---|
| `title not blank` | в Entity (текущая `Page` так и делает) |
| `slug matches /^[a-z0-9-]+$/` | в Entity или в VO `Slug` |
| `path uniqueness` | в `existsByPath()` repository + DB partial index |
| `status transitions` (только из Draft в Published?) | в Entity-метод `publish()`/`archive()` |
| `email format` | в VO `EmailAddress` |
| `not blank` для DTO | Symfony Validator на DTO |

## Domain exception

```php
final class ContentNotFoundException extends \DomainException
{
}
```

Все доменные ошибки — наследники `\DomainException` или своих базовых классов модуля. Контроллер маппит их в HTTP-коды.

## Anti-patterns

- **Anemic domain.** `Page` с одними `set*` без правил.
- **God-entity.** Page содержит PageBlock + Redirect + Sitemap логику.
- **Validator на Entity.** Validator constraints — для DTO. Domain валидируется через явный код.
- **Entity знает о Twig.** Никогда.
- **Entity делает Doctrine query.** Никогда. Это — repository.
- **Static factories с side effects.** Static factory должна быть pure (`Page::draft(...)`).

## Чек-лист добавления Domain-объекта

- [ ] Поля `private`, setter’ы по бизнес-смыслу.
- [ ] Инварианты в конструкторе + методах.
- [ ] Doctrine attributes — да; вызовы EM — нет.
- [ ] Repository interface в Domain/Repository.
- [ ] Конкретный Doctrine repository — в Infrastructure/Repository.
- [ ] Unit-тест на инварианты и переходы состояний.
- [ ] DTO output добавлен в Application/DTO.
- [ ] Документация модели в [05-domain-model](05-domain-model.md) обновлена.

## Связанные документы

- [05-domain-model](05-domain-model.md)
- [09-application-layer](09-application-layer.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
