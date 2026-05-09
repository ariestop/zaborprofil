# 46. Glossary

Общие термины проекта.

## Архитектура

| Термин | Значение |
|---|---|
| **Modular Monolith** | Один deployment unit, разбитый на bounded contexts (модули). У нас — `src/Module/<Name>` |
| **Bounded Context** | Граница смысловой области (Content, Seo, Lead, ...) |
| **Clean Architecture** | Слоистая модель: UI → Application → Domain ← Infrastructure |
| **Aggregate** | Корневая Entity, через которую только разрешено менять состояние внутри bounded context |
| **Value Object (VO)** | Immutable, без identity, сравнивается по значению |
| **Domain Event** | Immutable факт, что-то произошло в домене |
| **Application Handler** | Use case с одной публичной точкой входа |
| **DTO** | Data Transfer Object для входа/выхода |
| **Repository** | Контракт чтения/записи aggregate |
| **Anemic Domain** | Anti-pattern: Entity без поведения, только данные |
| **God Entity** | Anti-pattern: Entity с десятками зависимостей и методов |

## Symfony / стек

| Термин | Значение |
|---|---|
| **Kernel** | Точка входа Symfony |
| **Bundle** | Symfony-расширение (`framework-bundle`, `doctrine-bundle`, ...) |
| **Firewall** | Symfony Security слой авторизации |
| **Voter** | Класс для проверки разрешений на ресурс |
| **EventSubscriber** | Listener’ы Symfony Kernel/Doctrine событий |
| **Twig Extension** | Расширение Twig (функции/фильтры) |
| **Doctrine Naming Strategy** | Преобразование PHP-имён в SQL (`underscore_number_aware`) |
| **Doctrine Migrations** | Версионированные изменения схемы БД |
| **Symfony Cache Pool** | Именованный кеш с adapter и default TTL |
| **Messenger Transport** | Канал доставки сообщений (`async`, `failed`) |
| **AsMessageHandler** | Атрибут Symfony Messenger для регистрации handler |
| **AsCommand** | Атрибут для регистрации Console command |
| **ULID** | Universally unique sortable identifier (Symfony Uid) |

## Проект

| Термин | Значение |
|---|---|
| **Page** | Страница сайта в `Module\Content` |
| **PageBlock** | Блок страницы (hero/text/seo_text/...) |
| **Path** | URL страницы (хранится вручную) |
| **Slug** | Часть URL, kebab-case |
| **Status** | Жизненный цикл: Draft / Published / Archived |
| **Indexable** | Флаг разрешения индексации поисковиками |
| **Redirect** | Сущность 301/302 в `Module\Seo` |
| **Setting** | Ключ-значение конфигурации в `Module\Settings` |
| **AdminUser** | Учётка администратора (Symfony Security user) |
| **AdminPermission** | Enum прав в админке (`pages.publish`, `seo.edit`, ...) |
| **Content Engine** | Полная цепочка управления Page/PageBlock |
| **Admin API** | JSON API под `/admin/api/...` |
| **Public site** | SSR-зона публичного сайта |
| **Admin SPA** | Админ-интерфейс на React + TypeScript в каталоге `admin/` (Vite entry `admin/app.ts`) |
| **Vite manifest** | `public_html/build/.vite/manifest.json` для cache-busted asset URLs |

## DevOps

| Термин | Значение |
|---|---|
| **Release** | Папка `releases/<timestamp>` |
| **Current** | Symlink на активный release |
| **Shared** | Папка `shared/` с persistent данными между релизами |
| **Rollback** | Переключение `current` на предыдущий release |
| **Native deploy** | Без Docker, через nginx/php-fpm/systemd |
| **CI green** | Все CI jobs прошли |
| **CI gate** | Условие успешного CI run для допуска к deploy |
| **Healthcheck** | Endpoint/команда для проверки здоровья приложения |
| **Liveness/Readiness** | Готовность отвечать / готовность принимать трафик |
| **Logrotate** | Утилита ротации логов |
| **Mailpit** | Локальный SMTP test inbox |
| **Adminer** | UI для PostgreSQL (только локально) |

## Безопасность

| Термин | Значение |
|---|---|
| **CSRF** | Cross-Site Request Forgery; защита через token |
| **Same-origin** | Origin/Referer запроса совпадает с SITE_URL |
| **Login throttling** | Ограничение неудачных логинов |
| **RBAC** | Role-based access control |
| **Voter** | Класс с логикой "можно ли" |
| **HSTS** | Строгая HTTPS-политика |
| **PII** | Personally identifiable information |
| **Secrets** | Чувствительные значения (`APP_SECRET`, DB password) |

## SEO

| Термин | Значение |
|---|---|
| **Canonical** | Указание основного URL для дубликатов |
| **Sitemap** | XML список индексируемых URL |
| **Robots.txt** | Указания поисковикам, что можно/нельзя |
| **301 / 302** | Permanent / Temporary redirect |
| **Indexable** | Флаг "разрешено индексировать" |
| **Slug** | Kebab-case часть пути |
| **OpenGraph** | Метаданные для соцсетей |
| **JSON-LD** | Schema.org структурированные данные |
| **Core Web Vitals** | Google performance метрики |

## Сокращения

| Сокращение | Расшифровка |
|---|---|
| **CMS** | Content Management System |
| **SSR** | Server-Side Rendering |
| **SPA** | Single-Page Application |
| **DI** | Dependency Injection |
| **DTO** | Data Transfer Object |
| **VO** | Value Object |
| **ORM** | Object-Relational Mapping |
| **DBAL** | Database Abstraction Layer |
| **DSN** | Data Source Name |
| **CSRF** | Cross-Site Request Forgery |
| **XSS** | Cross-Site Scripting |
| **TTL** | Time To Live |
| **PR** | Pull Request |
| **CI/CD** | Continuous Integration / Continuous Delivery |
| **ADR** | Architecture Decision Record |
| **SLO** | Service-Level Objective |

## Связанные документы

- [00-overview](00-overview.md)
- [02-architecture](02-architecture.md)
- [03-project-structure](03-project-structure.md)
