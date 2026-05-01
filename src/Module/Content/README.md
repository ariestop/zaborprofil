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
