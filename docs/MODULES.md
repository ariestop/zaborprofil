# Модули

## Первый этап

- `Shared` — общие building blocks.
- `Admin` — dashboard и будущая shell-админка.
- `Auth` — авторизация администраторов.
- `User` — администраторы, роли и будущая связь с клиентами/партнерами.
- `Content` — базовое контентное ядро Page/PageBlock.

## Content Engine

Модуль `Content` реализует базовое контентное ядро:

- `Page` — страница с ручным `path`, `slug`, `h1`, статусом и типом.
- `PageBlock` — блок страницы с `content` и `settings` в JSONB.
- `PageStatus`, `PageType`, `BlockType` — enum для ключевых состояний и типов.
- `PageRepositoryInterface`, `PageBlockRepositoryInterface` — доменные контракты.
- `DoctrinePageRepository`, `DoctrinePageBlockRepository` — инфраструктурные реализации.
- Application commands и handlers для создания, обновления, публикации, архивации, управления блоками и сортировки.
- Admin API под `/admin/api/content/...`.
- Публичный Twig renderer опубликованных страниц.

Публичный renderer открывает только страницы со статусом `published`. Черновики и архивные страницы возвращают `404`.

## Следующие модули CMS

- `Media`
- `Seo`
- `Menu`
- `Settings`
- `Lead`
- `Portfolio`
- `Redirect`
- `AuditLog`

## Зарезервированные модули

- `Catalog`
- `Product`
- `Cart`
- `Order`
- `Customer`
- `Partner`

## Как добавлять модуль

1. Создать каталог `src/Module/ModuleName`.
2. Разделить код на `Domain`, `Application`, `Infrastructure`, `UI`.
3. Не добавлять зависимости на UI или Infrastructure внутрь Domain.
4. Добавить тесты.
5. Обновить документацию модуля.
