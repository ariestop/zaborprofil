# 09. Application layer

## Назначение

Application layer — **сценарии использования системы**: что мы умеем делать с доменом. Это место оркестрации, транзакционных границ и подготовки данных для UI.

## Состав

| Папка | Что внутри |
|---|---|
| `Application/Command/` | Input DTO для write-операции (`CreatePageCommand`) |
| `Application/Query/` | Input DTO для read-операции (целевое; сейчас часто решается напрямую в контроллере) |
| `Application/Handler/` | Use case (`CreatePageHandler::__invoke(CreatePageCommand)`) |
| `Application/DTO/` | Output DTO (`PageOutput`, `PageBlockOutput`) |
| `Application/Service/` | Application capability (`PublicPageResolver`, `SettingsRegistry`) |
| `Application/Exception/` | Application-specific exceptions |

## Конвенция handler

```php
namespace App\Module\Content\Application\Handler;

final class CreatePageHandler
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly EventDispatcherInterface $events, // целевое
    ) {
    }

    public function __invoke(CreatePageCommand $command): PageOutput
    {
        if ($this->pages->existsByPath($command->path)) {
            throw new ContentValidationException('page.path.duplicate');
        }

        $page = new Page(
            type: $command->type,
            title: $command->title,
            slug: $command->slug,
            path: $command->path,
            h1: $command->h1,
            template: $command->template,
            sortOrder: $command->sortOrder,
            indexable: $command->indexable,
        );

        $this->pages->save($page);

        return PageOutput::fromEntity($page);
    }
}
```

## Правила

- Один handler — один сценарий. Имя — глагольное (`CreatePage`, `PublishPage`).
- Handler не знает HTTP. Принимает только DTO/команду, не `Request`.
- Handler **не** импортирует Doctrine `EntityManagerInterface`. Persistence — через `*RepositoryInterface`.
- Handler возвращает либо `void`, либо output DTO. **Никогда** Doctrine Entity наружу — лоj (для UI/API превращается в DTO).
- Транзакционная граница — handler. Если нужно несколько save — оборачивать в транзакцию через специальный метод репозитория или application service. Запрещено вызывать `EntityManager->flush()` из контроллера.

## Где валидация

- Формат входа (`@Assert\NotBlank`, `@Assert\Length`, `@Assert\Choice`) — на DTO/Command.
- Бизнес-инварианты (уникальность, домен-правила) — внутри Domain Entity и/или handler.
- Валидация на DTO выполняется в контроллере **до** вызова handler.

## Idempotency

Целевое: критичные команды (`PlaceOrder`, `RegisterCustomer`, webhook handlers) принимают `idempotencyKey` и сохраняют записку об уже обработанных ключах. Без этого — duplicate processing после ретраев Messenger.

## Application services

Service ≠ Handler:

- Service — долгоживущая capability, без чёткого «один сценарий» (`PublicPageResolver`, `SettingsRegistry`).
- Handler — единичный сценарий (`CreatePageHandler`).

Если что-то называется `XService` и имеет 10 методов разной природы — это плохой код. Разбивайте на handler’ы.

## Где держать DTO

- Input DTO (Command/Query) — `Application/Command` или `Application/Query`. Для admin API часто это PHP-классы с `readonly` полями + Validator constraints.
- Output DTO — `Application/DTO`. Содержит только примитивы и другие DTO; никаких Doctrine collections наружу.

## Output DTO — пример

```php
final class PageOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $path,
        public readonly string $status,
        public readonly array $blocks,
        public readonly ?string $publishedAt,
    ) {
    }

    public static function fromEntity(Page $page): self
    {
        return new self(
            id: (string) $page->id(),
            title: $page->title(),
            path: $page->path(),
            status: $page->status()->value,
            blocks: array_map(PageBlockOutput::fromEntity(...), $page->blocks()),
            publishedAt: $page->publishedAt()?->format(\DATE_ATOM),
        );
    }
}
```

## Orchestration

Если сценарий требует нескольких aggregates / side-effects — это всё ещё один handler.

```php
public function __invoke(PublishPageCommand $cmd): PageOutput
{
    $page = $this->pages->getById($cmd->id);
    $page->publish();
    $this->pages->save($page);
    $this->cache->invalidatePage($page->path());
    $this->bus->dispatch(new PageWasPublished((string) $page->id()));
    return PageOutput::fromEntity($page);
}
```

## Events

Целевое: handler публикует доменные события через Symfony EventDispatcher или Messenger:

- `PageWasPublished`
- `RedirectWasCreated`
- `LeadWasSubmitted`

Подписчики (subscribers/handlers) живут в Infrastructure или в других модулях. Domain events не содержат HTTP/Twig.

## Примеры use case (целевые)

| Use case | Где |
|---|---|
| `CreatePageHandler` | реализован |
| `UpdatePageHandler` | реализован |
| `PublishPageHandler` | реализован |
| `ArchivePageHandler` | реализован |
| `CreatePageBlockHandler` | реализован |
| `UpdatePageBlockHandler` | реализован |
| `DeletePageBlockHandler` | реализован |
| `ReorderPageBlocksHandler` | реализован |
| `UpdateSeoMetadataHandler` | целевое |
| `CreateRedirectHandler` | целевое (есть Domain `Redirect`) |
| `CreateLeadHandler` | целевое |
| `UploadMediaAssetHandler` | целевое |
| `CreateProductHandler` | целевое |
| `PlaceOrderHandler` | целевое |

## Anti-patterns

- `App\Service\PageService` со 100 разнородных методов.
- Handler принимает `Request` вместо DTO.
- Handler возвращает Entity наружу.
- Handler сам делает HTTP/Twig вызовы.
- В DTO — Doctrine relations или callbacks.
- Бизнес-проверки в контроллере вместо handler.
- Handler без тестов unit/integration.

## Чек-лист нового use case

- [ ] Создан Command/Query DTO.
- [ ] Создан Handler с одним публичным методом.
- [ ] Validator constraints на DTO покрывают input.
- [ ] Handler работает только с интерфейсами и доменом.
- [ ] Output DTO `fromEntity()` готов.
- [ ] Unit-тест на handler с моками репозитория.
- [ ] Integration-тест на репозитории при необходимости.
- [ ] Functional-тест на контроллер, дёргающий handler.
- [ ] Документация модуля обновлена.

## Связанные документы

- [10-domain-layer](10-domain-layer.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
- [19-forms-dto-validation](19-forms-dto-validation.md)
- [30-error-handling](30-error-handling.md)
