# 04. Правила зависимостей между слоями

Этот документ — **жёсткий контракт**. Любое нарушение этих правил должно блокировать merge.

## Тезис

```text
UI -> Application -> Domain
                    ^
Infrastructure ------|  (implements Domain/Application interfaces)
```

- Domain — самый внутренний слой, ничего не знает о Symfony, Doctrine, HTTP, Twig, Redis, FS.
- Application — оркестрация, оперирует только Domain и интерфейсами.
- Infrastructure — реализует интерфейсы Domain/Application.
- UI — адаптер транспорта, вызывает Application.

## Allowed dependencies

| Слой | Может импортировать |
|---|---|
| Domain | PHP stdlib, `Symfony\Component\Uid`, Doctrine ORM **attributes** (для маппинга), `App\Shared\Domain\*` |
| Application | Domain (этого и shared), `Symfony\Component\Validator\Constraints`, PSR interfaces, чистые value-classes |
| Infrastructure | Application + Domain + любые внешние библиотеки (Doctrine, Predis, Symfony HTTP, Mailer) |
| UI | Application + Domain (для type hints) + Symfony HTTP/Routing/Security/Twig |

Доступ через DI: UI обычно не импортирует Infrastructure напрямую — только через интерфейс из Application/Domain. Исключение: тонкие admin-контроллеры в текущей реализации могут вызывать Application handler напрямую.

## Forbidden dependencies

| Откуда | Куда | Запрет |
|---|---|---|
| Domain | `Symfony\Component\HttpFoundation\*` | Нельзя |
| Domain | `Doctrine\ORM\EntityManagerInterface` (вызовы) | Нельзя (attributes можно) |
| Domain | `Twig\*` | Нельзя |
| Domain | `Predis\*` / `Redis` | Нельзя |
| Domain | Files / `fopen` / `file_get_contents` | Нельзя |
| Domain | `Symfony\Component\Mailer\*` | Нельзя |
| Application | `Symfony\Component\HttpFoundation\Request/Response` | Нельзя |
| Application | `Doctrine\ORM\EntityManagerInterface` | Нельзя (используется через repository interface) |
| Application | `Twig\*` | Нельзя |
| Application | конкретные `Doctrine*Repository` | Нельзя (только `*RepositoryInterface`) |
| Application | `Symfony\Bundle\*` | Нельзя |
| Controller | Конкретный `Doctrine*Repository`, `EntityManagerInterface` | Нельзя (вызывать через Application) |
| Controller | Бизнес-правила (вычисления, validation бизнеса) | Нельзя — только перенаправление |
| Twig template | Doctrine query, EntityManager, Repositories | Нельзя |
| Migration | `App\Module\*\Domain\*` | Нельзя (миграция — DBAL only) |
| EventSubscriber (бизнесовый) | `App\Module\*\Domain\Entity` без use case | Нельзя — заворачивать в Application handler |
| `App\Shared\*` | `App\Module\*` | Нельзя — shared не знает модулей |
| `App\Module\X` | `App\Module\Y\Domain\Entity` напрямую через persistence | Нельзя (через published контракт модуля Y) |

## Правила для подсистем

### Twig

- В Twig нельзя выполнять Doctrine query, считать сложные бизнес-вычисления.
- В Twig можно: фильтры, простые тернарные выражения, итерация по подготовленному view model.
- Сложная логика — переносится в Application/UI helper или Twig extension с явным контрактом.

### EventSubscriber

Допустимо использовать subscriber только для **инфраструктурных** задач:

- security headers (`SecurityHeadersSubscriber`),
- request_id (`RequestIdSubscriber`),
- admin CSRF / Origin (`AdminApiCsrfSubscriber`, `AdminApiOriginSubscriber`),
- 301 redirects (`RedirectKernelSubscriber`),
- admin no-index (`AdminNoIndexSubscriber`),
- Doctrine timestamp listener (`TimestampListener`).

**Запрещено** в subscriber вызывать Application handler напрямую без явной причины — это маскирует бизнес-логику.

### Repository

- Domain содержит `*RepositoryInterface` с минимально необходимыми методами (`find`, `save`, `remove`, доменные `findBy*`).
- Infrastructure содержит `Doctrine*Repository implements *RepositoryInterface`.
- Никаких generic `findAll()` без пагинации в production-коде.
- Никаких `EntityRepository`-наследников, отдающих `QueryBuilder` наружу — это утечка persistence в Domain.

### Entity

- Doctrine attributes на Entity допустимы (это маппинг, не I/O).
- Entity сама проверяет инварианты (см. [Page::__construct](../src/Module/Content/Domain/Entity/Page.php)).
- Setter’ы — по бизнес-смыслу (`publish()`, `archive()`), не `setStatus()` без правил.

### Контроллер

- Принимает `Request` или конкретный DTO.
- Парсит/валидирует вход.
- Вызывает один Application handler.
- Возвращает `Response` (HTML или JSON).
- Не делает SQL, не делает кеш-инвалидацию (это Application).

## Common violations

| Симптом | Что не так | Как исправить |
|---|---|---|
| `EntityManagerInterface` в контроллере | Бизнес-логика просочилась | Создать handler в Application |
| `Page::publish()` вызывается из subscriber | Скрытая бизнес-логика | Вызвать через `PublishPageHandler` |
| `Domain/Service` импортирует `RequestStack` | Утечка HTTP | Передавать данные явно как параметры |
| `Doctrine*Repository` тип-хинт в контроллере | Обход interface | Тип-хинт `*RepositoryInterface` или handler |
| Огромный `App\Service\PageService` | Свалка | Разбить на `Application\Handler\<Action>` |
| Doctrine attributes используют валидатор Symfony | Смешение validation и mapping | Validator на DTO, инварианты — в Entity |
| Twig вызывает `repository.findBy(...)` через global | Скрытый I/O в шаблоне | Готовить view model в Application/Controller |
| Migration работает с `App\Module\Content\Domain\Entity\Page` | Поломает миграцию при изменении модели | DBAL SQL only |
| `App\Shared\Infrastructure\X` импортирует `App\Module\Content` | Перевёрнутая зависимость | Перенести в модуль |

## How to fix violations

1. Найти границу слоя, которая нарушена.
2. Определить, где должна жить логика по правилам выше.
3. Извлечь в нужный слой (handler, repository, voter, subscriber).
4. Проверить тестом: unit на Domain, application-level на handler, functional на контроллер.
5. Запустить `vendor/bin/phpstan analyse` и `vendor/bin/rector process --dry-run`.
6. Обновить смежные документы, если изменился контракт.

## Целевое state: автоматический контроль

- Включить `phpstan-deprecation-rules` (уже частично).
- Целевое: добавить deptrac или `phpat` rules-set (см. [45-roadmap-and-extension-points](45-roadmap-and-extension-points.md)) для статической проверки границ слоёв.
- В CI добавить `phpat` job до merge.

## Связанные документы

- [02-architecture](02-architecture.md)
- [03-project-structure](03-project-structure.md)
- [09-application-layer](09-application-layer.md)
- [10-domain-layer](10-domain-layer.md)
- [11-infrastructure-layer](11-infrastructure-layer.md)
- [40-cursor-rules](40-cursor-rules.md)
