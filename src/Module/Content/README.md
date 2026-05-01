# Content

Модуль контентного ядра CMS.

## Реализовано

- `Page`
- `PageBlock`
- enum `PageStatus`, `PageType`, `BlockType`
- repository interfaces
- Doctrine repositories
- application commands и handlers
- Admin API
- публичный Twig renderer

`Page.path` уникален и задается вручную. Публичный renderer показывает только страницы в статусе `published`.

## Структура

- `Domain` — сущности, enum, repository interfaces и доменные исключения.
- `Application` — команды, handlers, DTO и public page resolver.
- `Infrastructure` — Doctrine repositories.
- `UI/Admin` — JSON API для будущей Vue-админки.
- `UI/Web` — публичный renderer и Twig block renderer.

## Admin API

- `POST /admin/api/content/pages`
- `PUT /admin/api/content/pages/{id}`
- `POST /admin/api/content/pages/{id}/publish`
- `POST /admin/api/content/pages/{id}/archive`
- `POST /admin/api/content/pages/{pageId}/blocks`
- `PUT /admin/api/content/blocks/{id}`
- `POST /admin/api/content/pages/{pageId}/blocks/reorder`
- `DELETE /admin/api/content/blocks/{id}`

## Публичный Рендер

Публичный catch-all route ищет опубликованную страницу по `Page.path`. Если страницы нет, статус не `published` или страница архивирована, возвращается `404`.

Блоки сортируются по `position`. Блоки с `is_enabled=false` не рендерятся.
