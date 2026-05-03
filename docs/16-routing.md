# 16. Routing

## Источник истины

`config/routes.yaml` использует attribute-loader для `src/`. Каждый контроллер сам объявляет свои роуты через `#[Route(...)]`.

Дополнительно подгружаются `config/routes/security.yaml` и `config/routes/framework.yaml` (стандартные Symfony).

## Конвенции

| Префикс | Зона | Auth | Формат |
|---|---|---|---|
| `/` | Front | public | HTML |
| `/health` | Operational | public | JSON |
| `/sitemap.xml`, `/robots.txt` | SEO | public | XML/TXT |
| `/admin/login` | Auth | public | HTML |
| `/admin` | Admin HTML | `ROLE_ADMIN` | HTML |
| `/admin/api` | Admin JSON API | `ROLE_ADMIN` + CSRF + Origin | JSON |
| `/api/v1` (целевое) | Public API | token / JWT | JSON |
| `/_profiler`, `/_wdt` | Dev only | `APP_ENV=dev` | HTML |
| `/webhooks/<source>` (целевое) | Integrations | HMAC | JSON |

## Имена роутов

`<area>_<resource>_<action>`. Примеры:

- `admin_login`, `admin_logout`, `admin_dashboard`
- `health_check`
- `content_public_page`
- `seo_sitemap`, `seo_robots`
- `admin_content_pages_create`, `admin_content_pages_publish`

В Twig используется `path('admin_dashboard')`, `url('content_public_page', {path: page.path})`.

## Приоритеты

`PublicPageController` использует `priority: -100` для catch-all `/{path}`. Это **обязательно**, иначе catch-all перехватывает `/admin`, `/api`, `/sitemap.xml` и т.д.

```mermaid
flowchart LR
    Req[Incoming /admin/...] --> AdminRoutes[Admin routes priority 0]
    Req --> Sitemap[/sitemap.xml/]
    Req --> Health[/health/]
    Req --> CatchAll[/{path} priority -100]
    AdminRoutes --> R[Routed first]
```

## Method restrictions

Каждый роут явно указывает `methods`. По умолчанию Symfony роутит на любой метод — это уязвимость.

```php
#[Route('/admin/api/content/pages', methods: ['POST'])]
#[Route('/admin/api/content/pages/{id}', methods: ['PUT'])]
```

## Requirements / Path constraints

- Catch-all: `requirements: ['path' => '.*']`.
- ID-параметры: `requirements: ['id' => '[0-9A-Z]{26}']` (для ULID).
- Slug: `requirements: ['slug' => '[a-z0-9-]+']`.

## Hostname routing

Не используется. Мульти-домен — целевое для будущих B2B-кабинетов; сейчас только `zaborprofil.ru`.

## Anti-patterns

- Catch-all без приоритета.
- Catch-all с `methods` не указанным — POST уходит туда же, что и GET.
- Дублирующиеся имена роутов (`name: 'page'` в двух модулях).
- Реврайт через `.htaccess`/nginx того, что должен делать Symfony Router.
- Жёстко прописанные URL в Twig (`<a href="/admin/dashboard">`) вместо `path('admin_dashboard')`.

## Чек-лист новой роуты

- [ ] Префикс соответствует зоне.
- [ ] `name` соответствует конвенции.
- [ ] `methods` указан явно.
- [ ] `requirements` для динамических параметров.
- [ ] `priority` указан, если может конфликтовать с catch-all.
- [ ] `#[IsGranted(...)]` для admin/non-public роутов.
- [ ] В Twig вызовы через `path()`/`url()`, не hardcoded.
- [ ] Functional-тест на 200/404/403.

## Связанные документы

- [08-controller-architecture](08-controller-architecture.md)
- [12-admin-area](12-admin-area.md)
- [13-front-area](13-front-area.md)
- [14-api-area](14-api-area.md)
