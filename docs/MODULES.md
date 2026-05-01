# Модули

## Первый этап

- `Shared` — общие building blocks.
- `Admin` — dashboard и будущая shell-админка.
- `Auth` — авторизация администраторов.
- `User` — администраторы, роли и будущая связь с клиентами/партнерами.

## Следующие модули CMS

- `Content`
- `Page`
- `PageBlock`
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
