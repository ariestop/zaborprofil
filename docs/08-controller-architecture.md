# 08. Controller architecture

## Виды контроллеров

| Тип | Место | Префикс роута | Auth | Формат |
|---|---|---|---|---|
| Front | `src/Module/*/UI/Web` или `src/Shared/UI/Web` | без префикса (catch-all с low priority) | публично | HTML |
| Admin HTML | `src/Module/*/UI/Admin` | `^/admin` (без `/api`) | `ROLE_ADMIN` через firewall | HTML |
| Admin API | `src/Module/*/UI/Admin` | `^/admin/api` | `ROLE_ADMIN` + CSRF + Origin | JSON |
| Public API | `src/Module/*/UI/Api` (целевое) | `^/api` | токен/JWT | JSON |
| Dev | `dev`-bundle (`web-profiler-bundle`, `WebProfilerBundle`) | `^/_(profiler\|wdt)` | `APP_ENV=dev/test` | HTML/JSON |
| Console | `src/Module/*/UI/Console` (целевое) или `src/Shared/UI/Console` | n/a | OS user | text |

## Правила именования

- Класс: `<Subject><Verb>Controller` или `<Subject>ApiController` для JSON. Примеры: `PageApiController`, `SitemapController`, `RobotsController`, `SecurityController`.
- Метод: `__invoke` (single-action — рекомендуется), либо чёткое имя `create`, `update`, `publish`.
- Route name: `<area>_<resource>_<action>`, например `admin_content_pages_create`, `content_public_page`, `health_check`.

## Что контроллер ДОЛЖЕН делать

1. Принять `Request` или DTO.
2. Авторизоваться (`#[IsGranted('...')]` или voter, если не покрыт firewall’ом).
3. Распарсить вход и валидировать (Validator).
4. Вызвать **один** Application handler/service.
5. Вернуть Response (HTML/JSON), маппинг ошибок в HTTP-коды.

Тонкий контроллер обычно укладывается в 5–25 строк.

## Что контроллер НЕ должен делать

- Делать SQL или вызывать `EntityManagerInterface`.
- Делать `cache->get()` или `redis->set()`.
- Запускать Mailer / Messenger напрямую (это Application).
- Содержать бизнес-валидации (только формат входа).
- Иметь private методы с бизнес-логикой.
- Читать `$_SESSION` или работать с suricons; для CSRF — Symfony API.
- Возвращать «сырые» исключения наружу.

## Front controllers

### Пример: `PublicPageController`

```13:32:src/Module/Content/UI/Web/PublicPageController.php
final class PublicPageController extends AbstractController
{
    #[Route('/{path}', name: 'content_public_page', requirements: ['path' => '.*'], priority: -100, methods: ['GET'])]
    public function __invoke(string $path, PublicPageResolver $resolver, TwigBlockRenderer $blockRenderer): Response
    {
        $page = $resolver->resolve($path);

        if ($page === null) {
            throw $this->createNotFoundException('Page not found.');
        }
        // ...
    }
}
```

Правила Front:

- `priority: -100` — чтобы catch-all не перехватывал admin/API/sitemap/robots.
- Нет CSRF (только GET).
- Нет привязки к admin firewall — anonymous допустим.
- Если путь не найден или статус не Published — `404`.

## Admin controllers

### HTML admin

`DashboardController` — рендерит admin shell, прокидывает CSRF token в `<meta>`.

### Admin API

- Префикс роутов: `/admin/api/<module>/<resource>`.
- `Content-Type: application/json`.
- Парсинг тела: через `JsonRequest::parse(...)`.
- Ответ: через `ContentApiResponder` (унифицированный JSON success/error).
- 500 ошибки заменяются на `Internal server error` с логированием оригинала.

Конкретные правила безопасности — [20-security-and-access-control](20-security-and-access-control.md).

## API controllers (целевое)

Для публичных API:

- Префикс `/api/v1/...`.
- Auth через Bearer token / JWT.
- Rate limiting через `symfony/rate-limiter`.
- CORS контролируется на nginx или kernel.response listener.
- Никогда не отдавать Doctrine entity напрямую — всегда через DTO.

## Dev controllers

Только в `dev`/`test`. Ограничено `bundles.php`:

- `WebProfilerBundle::class => ['dev' => true, 'test' => true]`
- `DebugBundle::class => ['dev' => true, 'test' => true]`

В production `^/_(profiler|wdt)` должен возвращать 404.

## Webhooks (целевое)

- Префикс `/webhooks/<source>` без firewall.
- Аутентификация: HMAC signature/secret.
- Идемпотентность по headers (`Idempotency-Key`) и/или request id источника.
- Логирование канал `business`/`integration`.

## Console commands (целевое расширение)

```text
src/Module/<Name>/UI/Console/
└── <Action>Command.php
```

- Класс наследует `Symfony\Component\Console\Command\Command` (атрибут `#[AsCommand]`).
- В `execute()` — только парсинг + вызов handler.
- Возвращает `Command::SUCCESS` / `FAILURE`.

## Anti-patterns

| Симптом | Что не так | Решение |
|---|---|---|
| `$em->getRepository(Page::class)->findBy(...)` в контроллере | Прямой Doctrine | Тонкий контроллер + `Application\Handler` |
| 200 строк кода в `__invoke` | Толстый контроллер | Вытащить в handler / service |
| Тяжёлый `if`-tree на статус Page внутри контроллера | Бизнес-логика | Метод `Page::publish()`, handler |
| `try { ... } catch (\Throwable $e) { return new Response($e->getMessage(), 500); }` | Утечка деталей | Централизованный responder + log |
| Контроллер импортирует `DoctrinePageRepository` | Обход interface | Тип-хинт интерфейса |
| Twig template вызывает `app.user` для проверки прав | Нет — лучше через voter в helper | Использовать `is_granted(...)` |
| Контроллер вручную инвалидирует Redis | Лучше event subscriber или handler | См. [23-cache-and-redis](23-cache-and-redis.md) |
| Смешивание Admin и Front в одном контроллере | Размывание зон | Разделить файлы и неймспейсы |

## Common mistakes (при добавлении контроллера)

- Забыли `priority: -100` для catch-all — перехватываются другие роуты.
- Забыли `methods: ['GET']` — GET-только страница принимает POST.
- Забыли `#[IsGranted('...')]` для admin action — полагаемся только на firewall, ломается с другой firewall-структурой.
- Забыли проверить `null` от resolver’а — 500 вместо 404.
- Забыли `JsonRequest::parse` для admin API — и работают с raw `Request->getContent()`.
- Кладут контроллер в `App\Controller` — нарушение модульности (нет такой папки в проекте).

## Чек-лист добавления нового контроллера

- [ ] Принадлежность модулю определена (`UI/Admin`, `UI/Web`, `UI/Api`).
- [ ] Имя класса и роута соответствует конвенциям.
- [ ] HTTP-метод указан явно.
- [ ] Авторизация: `#[IsGranted('...')]` или firewall.
- [ ] Валидация ввода — DTO + Validator.
- [ ] Вызывается ровно один handler/service.
- [ ] Ошибки маппятся в HTTP-коды.
- [ ] Логирование через Monolog с правильным каналом.
- [ ] Functional-тест добавлен.
- [ ] Документация модуля обновлена.

## Связанные документы

- [09-application-layer](09-application-layer.md)
- [12-admin-area](12-admin-area.md)
- [13-front-area](13-front-area.md)
- [14-api-area](14-api-area.md)
- [16-routing](16-routing.md)
- [20-security-and-access-control](20-security-and-access-control.md)
