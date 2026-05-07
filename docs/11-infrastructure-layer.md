# 11. Infrastructure layer

## Назначение

Infrastructure реализует контракты Application/Domain поверх внешних технологий: Doctrine, Redis, Mailer, Filesystem, HTTP, Telegram.

## Состав (фактическое + целевое)

| Папка | Что внутри |
|---|---|
| `Infrastructure/Doctrine/` | listeners, mappings (если не attributes), naming strategies (`TimestampListener`, `PagePathChangeListener`) |
| `Infrastructure/Repository/` | реализации `*RepositoryInterface` (`DoctrinePageRepository`, `DoctrinePageBlockRepository`, `DoctrineSettingRepository`, `DoctrineRedirectRepository`, `AdminUserRepository`) |
| `Infrastructure/Http/` | EventSubscriber модуля (`AdminApiCsrfSubscriber`, `AdminApiOriginSubscriber`, `AdminNoIndexSubscriber`, `RedirectKernelSubscriber`, `SecurityHeadersSubscriber`, `RequestIdSubscriber`) |
| `Infrastructure/Security/` | voters (`AdminPermissionVoter`), user checkers (`AdminUserChecker`) |
| `Infrastructure/Logging/` | Monolog handlers, processors, messages (`TelegramErrorHandler`, `PiiRedactorProcessor`, `RequestProcessor`, `UserProcessor`, `ReleaseProcessor`) |
| `Infrastructure/Upload/` | `UploadValidator`, `ValidatedUpload`, `UploadSecurityException` |
| `Infrastructure/Cache/` (целевое) | `CacheInvalidator`, обёртки над пулами Symfony Cache |
| `Infrastructure/Mailer/` (целевое) | адаптер Symfony Mailer + шаблоны транзакций |
| `Infrastructure/FileStorage/` (целевое) | абстракция local/S3 |
| `Infrastructure/Integration/` (целевое) | HTTP-клиенты к внешним API |

## Doctrine repositories

Класс — implements interface, `final`, через DI.

```php
final class DoctrinePageRepository extends ServiceEntityRepository implements PageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function getById(Ulid $id): Page
    {
        $page = $this->find($id);
        if ($page === null) {
            throw new ContentNotFoundException();
        }

        return $page;
    }

    public function findByPath(string $path): ?Page
    {
        return $this->findOneBy(['path' => $path, 'deletedAt' => null]);
    }

    public function existsByPath(string $path): bool
    {
        return null !== $this->findOneBy(['path' => $path, 'deletedAt' => null]);
    }

    public function save(Page $page): void
    {
        $this->getEntityManager()->persist($page);
        $this->getEntityManager()->flush();
    }

    public function remove(Page $page): void
    {
        $this->getEntityManager()->remove($page);
        $this->getEntityManager()->flush();
    }
}
```

Правила:

- Не возвращать `QueryBuilder` наружу.
- Не сериализовать DTO внутри репозитория — это Application.
- Не складывать сложную бизнес-логику в репозиторий.
- Для тяжёлых выборок — отдельные методы с явным контрактом и индексами в БД.

### Регистрация в DI

Через `services.yaml`:

```yaml
App\Module\Content\Domain\Repository\PageRepositoryInterface:
    alias: App\Module\Content\Infrastructure\Repository\DoctrinePageRepository
```

Или через `#[AsAlias]` на Doctrine repository (целевое — рассмотреть в [40-cursor-rules](40-cursor-rules.md)).

## EventSubscriber (модульный)

Допустимо для **инфраструктурных** задач:

- CSRF / Origin для admin API.
- Security headers, no-index для admin.
- Request id, correlation id.
- Doctrine timestamps.
- 301 редиректы для `Redirect`.

Запрещено в subscriber:

- Бизнес-логика без явного use case.
- Скрытое мутирование domain.
- Прямой `EntityManager->flush()` без понимания границ транзакции.

## Mailer / Messenger / FileStorage / HTTP клиенты

- **Mailer:** обёртка `MailerInterface` (Symfony) + DTO-сообщения в `Application` (`SendLeadConfirmation`).
- **Messenger:** dispatch — из Application; handler сам в `Infrastructure` или соответствующем модуле, если это side effect.
- **FileStorage:** интерфейс `FileStorageInterface` (целевое) с реализацией `LocalFileStorage` сейчас и `S3FileStorage` потом.
- **HTTP-клиенты:** `Symfony\Contracts\HttpClient\HttpClientInterface` обёрнут в integration-class с timeout, retry и логированием.

## Cache (Redis)

См. [23-cache-and-redis](23-cache-and-redis.md).

- Symfony Cache pools `cache.public_page`, `cache.settings`, `cache.menu`, `cache.seo`.
- Тип-хинт: `CacheInterface` (через DI с `#[Target('public_page')]`).
- Никаких прямых вызовов `Predis\Client` из Application — только через Symfony Cache adapter.

## Logging

См. [28-logging-observability](28-logging-observability.md).

- Каналы: `audit`, `admin`, `seo`, `lead`, `media`, `deploy`, `business`, `critical`.
- Critical handler — `TelegramErrorHandler` (отправляет через Messenger как `SendTelegramLogMessage`).
- Processors: `RequestProcessor`, `UserProcessor`, `ReleaseProcessor`, `PiiRedactorProcessor`.

## Upload

`UploadValidator` (`src/Shared/Infrastructure/Upload/UploadValidator.php`):

- Проверяет MIME через `finfo`.
- Проверяет размер.
- Проверяет расширение в whitelist.
- Возвращает `ValidatedUpload`.
- На нарушении — `UploadSecurityException`.

См. [25-files-and-uploads](25-files-and-uploads.md).

## Security

- `AdminPermissionVoter` — централизованная проверка прав по `AdminPermission` enum.
- `AdminUserChecker` — блокирует неактивных админов.
- Configure в `services.yaml` без аргументов (autoconfigure).

## Тестирование Infrastructure

| Что | Тип теста |
|---|---|
| Doctrine repository | Integration test с тестовой БД (миграции применяются) |
| Subscriber | Functional test с реальным Kernel |
| Mailer | Integration через `mailer.transport_factory.test` или Mailpit |
| HTTP клиент | Unit с `MockHttpClient` |
| Telegram handler | Unit с моком `MessageBusInterface` |

## Mocking

- В unit-тестах handler’ов мокается interface (`PageRepositoryInterface`), не Doctrine class.
- Не мокать `\DateTimeImmutable`, `Ulid` — это инфраструктурные типы.
- Не мокать Doctrine `EntityManagerInterface` напрямую — слишком хрупко.

## Anti-patterns

- Бизнес-логика в EventSubscriber.
- Repository, отдающий QueryBuilder наружу.
- Прямой `Predis\Client` в Application.
- Mailer-вызов из Domain.
- Catch-all `try/catch (\Throwable)` без логирования.

## Чек-лист новой Infrastructure-обвязки

- [ ] Реализован интерфейс из Domain/Application.
- [ ] Класс `final`.
- [ ] Зависимости — через DI, без `new`.
- [ ] Не утекают типы Doctrine/Redis наружу.
- [ ] Тесты integration/unit добавлены.
- [ ] Logging добавлен.
- [ ] Если новая интеграция — добавлены timeout, retry, idempotency.

## Связанные документы

- [10-domain-layer](10-domain-layer.md)
- [09-application-layer](09-application-layer.md)
- [17-doctrine-and-database](17-doctrine-and-database.md)
- [23-cache-and-redis](23-cache-and-redis.md)
- [24-messenger-and-queues](24-messenger-and-queues.md)
