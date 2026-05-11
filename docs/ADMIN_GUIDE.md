# Руководство администратора

На текущем этапе доступны:

- страница входа `/admin/login`;
- защищенный dashboard `/admin/dashboard`;
- страницы и блоки `/admin/pages`;
- структурированный builder страницы `/admin/pages/{id}/builder`;
- Media Library `/admin/media` с variants для изображений;
- управляемые меню `/admin/menu` для `header`, `footer`, `service`;
- заявки `/admin/leads` со статусами `new`, `in_progress`, `done`, `spam` и spam score/reasons;
- настройки, редиректы, health center, maintenance mode и audit log.

## Визуальный редактор блоков

В разделе `/admin/pages` для блоков `text` и `text_image` доступно два режима:

- `Визуально` — оператор работает с полями формы и форматированием текста, без ручного JSON;
- `JSON` — режим для продвинутого редактирования полной структуры блока.

Что доступно в визуальном режиме:

- поле заголовка;
- rich-text поле TipTap для текста (жирный, курсив, H2/H3, маркированный и нумерованный списки, цитата, ссылка);
- для `text_image` — выбор изображения из Media Library и заполнение `alt`.
- набор в rich-text поле выполняется в стандартном режиме слева-направо (LTR);
- при наборе в rich-text каретка смещается вправо, без «залипания» в начале строки;
- сохранённый текст блока подгружается сразу при первом открытии модального редактора.
- HTML форматирования сохраняется в `content.text` и не требует изменений Content API.

Рекомендация для операторов: использовать `Визуально` как основной режим, а `JSON` — только если нужно добавить нестандартные поля, которых нет в форме.

## Page Builder и legacy-редактор

В админке сейчас существуют два интерфейса редактирования блоков:

- `Page Builder` (`/admin/pages/{id}/builder`) — основной интерфейс для структурированной работы с блоками;
- legacy-редактор (`/admin/pages`, модалка `Редактор блоков`) — fallback/экспертный режим с прямым JSON.

Для блока `Слайдер` доступны пресеты в обоих интерфейсах:

- в builder: кнопки пресетов в `SliderEditor`;
- в legacy: секция `Пресеты слайдера` внутри модалки редактирования блока.

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
