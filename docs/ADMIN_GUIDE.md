# Руководство администратора

На текущем этапе доступны:

- страница входа `/admin/login`;
- защищенный dashboard `/admin/dashboard`;
- базовый Admin API для Content Engine под `/admin/api/content/...`.

## Content API

API пока предназначен для будущего Vue-интерфейса админки. Он работает через текущую admin-сессию Symfony Security.

Доступные сценарии:

- создать страницу;
- обновить страницу;
- опубликовать страницу;
- архивировать страницу;
- добавить блок;
- обновить блок;
- пересортировать блоки;
- удалить блок.

Основные endpoints:

- `POST /admin/api/content/pages`
- `PUT /admin/api/content/pages/{id}`
- `POST /admin/api/content/pages/{id}/publish`
- `POST /admin/api/content/pages/{id}/archive`
- `POST /admin/api/content/pages/{pageId}/blocks`
- `PUT /admin/api/content/blocks/{id}`
- `POST /admin/api/content/pages/{pageId}/blocks/reorder`
- `DELETE /admin/api/content/blocks/{id}`

## Следующие разделы админки

Полноценный Vue UI будет добавляться по этапам:

- страницы и блоки;
- медиа;
- SEO;
- меню;
- формы и заявки;
- портфолио;
- редиректы;
- настройки;
- пользователи и роли;
- audit log.

До реализации seed-команды администратора нужно создать вручную через миграцию, SQL или будущую console-команду.
