# Руководство администратора

На текущем этапе доступны:

- страница входа `/admin/login`;
- защищенный Vue dashboard `/admin/dashboard`;
- страницы и блоки `/admin/content/pages`;
- Media Library `/admin/media` с variants для изображений;
- управляемые меню `/admin/menu` для `header`, `footer`, `service`;
- заявки `/admin/leads` со статусами `new`, `in_progress`, `done`, `spam` и spam score/reasons;
- настройки, редиректы, health center, maintenance mode и audit log.

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
- получить preview-ссылку для черновика.

Основные endpoints:

- `POST /admin/api/content/pages`
- `PUT /admin/api/content/pages/{id}`
- `POST /admin/api/content/pages/{id}/publish`
- `POST /admin/api/content/pages/{id}/archive`
- `GET /admin/api/content/pages/{id}/preview-link`
- `POST /admin/api/content/pages/{pageId}/blocks`
- `PUT /admin/api/content/blocks/{id}`
- `POST /admin/api/content/pages/{pageId}/blocks/reorder`
- `DELETE /admin/api/content/blocks/{id}`

## Media, Menu и Lead API

Новые launch-ready разделы используют текущую admin-сессию и CSRF-защиту:

- `GET|POST /admin/api/media/assets`, `DELETE /admin/api/media/assets/{id}`;
- `GET|POST /admin/api/menu/items`, `PUT|DELETE /admin/api/menu/items/{id}`;
- `GET /admin/api/leads`, `PATCH /admin/api/leads/{id}/status`.

Публичные заявки отправляются в `POST /api/leads` с обязательным `consent=true` и honeypot-полем `website`.

## Release readiness

Перед выкладкой:

- `make quality` должен проходить локально или в CI;
- `php bin/console app:smoke:test --env=prod` должен проходить на целевом окружении;
- после restore rehearsal проверяются sitemap, login, preview links, Media Library и публичная lead-форма.

До реализации seed-команды администратора нужно создать вручную через миграцию, SQL или будущую console-команду.
