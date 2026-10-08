# Медиатека и MediaPicker

Раздел `/admin/media` — рабочая медиатека поверх Admin API `/admin/api/media/assets`. Тот же компонент выбора файла (`MediaPicker`) используется в блоках Page Builder, в TipTap и в полях изображений страницы.

## Admin API

| Метод    | URL                                   | Право          | Описание                                                                                                  |
| -------- | ------------------------------------- | -------------- | --------------------------------------------------------------------------------------------------------- |
| `GET`    | `/admin/api/media/assets`             | `media.upload` | Список с пагинацией, поиском, фильтрами и сортировкой                                                     |
| `POST`   | `/admin/api/media/assets`             | `media.upload` | Загрузка файла (`multipart/form-data`, поле `file`, опционально `folder`) с дедупликацией по хешу         |
| `PATCH`  | `/admin/api/media/assets/{id}`        | `media.upload` | Частичное изменение `alt`, `title`, `description`, `folder` (пустая строка или `null` очищают поле)       |
| `DELETE` | `/admin/api/media/assets/{id}`        | `media.delete` | Удаление записи и файлов (оригинал + variants). Используемый файл: `409 MEDIA_IN_USE`, `?force=1` удаляет |
| `GET`    | `/admin/api/media/assets/{id}/usages` | `media.upload` | Где используется файл: `{"total", "usages": [...]}`                                                       |
| `GET`    | `/admin/api/media/folders`            | `media.upload` | Список папок с количеством файлов: `{"folders": [{"name", "count"}]}`                                     |

Параметры `GET /admin/api/media/assets`:

| Параметр  | Значения                                                                       | По умолчанию |
| --------- | ------------------------------------------------------------------------------ | ------------ |
| `page`    | целое `>= 1`                                                                   | `1`          |
| `perPage` | `1..100` (в UI: 24 / 48 / 96)                                                  | `24`         |
| `q`       | подстрока в `originalName`, `alt`, `title` или `description` (до 100 символов) | пусто        |
| `type`    | `image` (MIME `image/*`) или `document` (остальное, сейчас PDF)                | все файлы    |
| `format`  | `jpeg`, `png`, `webp`, `avif`, `pdf`                                           | все форматы  |
| `folder`  | имя папки или `__none__` (файлы без папки)                                     | все папки    |
| `usage`   | `used` (есть использование на сайте) или `unused`                              | все файлы    |
| `from`    | дата загрузки не раньше `YYYY-MM-DD`                                           | без границы  |
| `to`      | дата загрузки не позже `YYYY-MM-DD` (включительно)                             | без границы  |
| `sort`    | `newest`, `oldest`, `name`, `size`, `size_asc`                                 | `newest`     |

Ответ: `{"assets": [...], "pagination": {"page", "perPage", "total", "totalPages"}}`. Элемент `assets` содержит `id`, `originalName`, `filename`, `publicPath`, `mimeType`, `size`, `width`, `height`, `variants`, `alt`, `title`, `description`, `folder`, `fileHash`, `focalX`, `focalY`, `usageCount`, `createdAt`. Некорректные параметры (в том числе неверная дата или `from > to`) дают `422` `{"error", "code": "VALIDATION"}`.

Ошибки загрузки и изменения имеют единый формат `{error, code}` (`AdminApiErrorResponder`, см. [12-admin-area](../12-admin-area.md)): нет поля `file` — `400 BAD_REQUEST`, файл больше лимита PHP — `422 FILE_TOO_LARGE`, недопустимый тип/размер/размеры — `422 VALIDATION`, неизвестный `id` — `404 NOT_FOUND`, удаление используемого файла без `force` — `409 MEDIA_IN_USE`.

## Метаданные

Поля `alt`, `title`, `description` (до 2000 символов), `folder` (до 120 символов, без `/`, `\` и управляющих символов; значение `__none__` зарезервировано под фильтр) хранятся в `media_assets` (nullable; миграции `Version20261006090000` и `Version20261007100000`). Папки плоские: отдельной сущности нет, список строится по значениям `folder`. `PATCH` частичный: переданные ключи меняются, остальные сохраняются. Размеры, тип, вес и дата загрузки показываются в панели свойств и в режиме «Список».

## Фокальная точка и адаптивные изображения

`PATCH` принимает `focalX`/`focalY` — целые проценты `0–100` от ширины и высоты изображения (`null` очищает обе координаты; передавать нужно обе, иначе `422`). В панели свойств файла фокальная точка задаётся кликом по превью, кнопка «Сбросить» возвращает кадрирование по центру.

На публичном сайте блоки `image`, `text_image`, `gallery`, `slider` и `hero` (необязательное поле `image` — фоновая картинка) выводят изображения Twig-функцией `responsive_image(src, options)`:

- блоки хранят прямой URL, поэтому ассет находится по точному `publicPath` оригинала (`ResponsiveImageResolver`, индекс `idx_media_assets_public_path`, результат кэшируется только в памяти запроса); URL не из медиатеки выводится обычным `<img>`;
- для найденного ассета рендерится `<picture>`: `<source>` для AVIF и WebP с `srcset` по ширинам из `variants` и `sizes` (по умолчанию `(min-width: 1152px) 1152px, 100vw`, в шаблонах блоков заданы свои); перед правилами `sizes` добавляются их копии для экранов от 2,5 dppx (DPR 3) со слотом 2/3 — такие телефоны получают картинку как для DPR 2, разница на глаз почти не видна, а вес меньше, `<img>` с оригиналом как fallback, обязательные `width`/`height` (защита от CLS) и `object-position` из фокальной точки;
- `alt`: явный `alt` блока, затем `alt` ассета, затем запасной текст (название блока); для декоративных картинок (фон `hero`) `alt=""`;
- первый блок страницы (`above_fold`) отдаёт главное изображение с `loading="eager"` и `fetchpriority="high"` (LCP), для первых трёх картинок галереи — только `eager`; всё остальное — `loading="lazy"` и `decoding="async"`.

Опции `responsive_image`: `alt`, `fallback_alt`, `decorative`, `class`, `sizes`, `priority`, `eager`. Разметку собирает `PictureHtmlBuilder`.

## Превью для всех картинок сайта: `app:media:sync`

`<picture>` со `srcset` строится только для ассетов медиатеки с вариантами. Поэтому:

- при загрузке `MediaOptimizer` делает WebP (и AVIF, если его умеет PHP) шириной 320, 480, 768, 1024 и 1280 px (промежуточные 480 и 1024 — чтобы телефон с плотным экраном не брал сразу 1280); если оригинал не шире 1280 px, в превью попадает и его собственная ширина, чтобы на широком экране не отдавалось уменьшенное 768 px;
- команда `bin/console app:media:sync` (опция `--dry-run` только показывает изменения):
    - заносит в медиатеку файлы из `/uploads/media/`, на которые ссылается контент, но которых нет в медиатеке (перенесённые вручную или импортом), — папка `imported`, с превью;
    - создаёт недостающие превью: картинкам без вариантов или без какой-то из нужных ширин (загруженным, когда в PHP не было WebP, или до появления 480/1024 px); без WebP/AVIF в PHP шаг пропускается, чтобы не перекодировать оригиналы зря;
    - пропускает отсутствующие на диске файлы, не-картинки и копии уже загруженных файлов под другим путём (по `sha256`), перечисляет их в отчёте;
    - повторный запуск ничего не меняет.
- Команда запускается после каждого деплоя (`tools/deploy/deploy-beget.sh`, `run_release_console_tasks` в `tools/deploy/common.sh`); ошибка не срывает деплой, страницы просто отдадут оригиналы.

## Где используется

`MediaUsageFinder` (`src/Module/Media/Application/Usage/`) собирает ссылки на файлы у провайдеров `MediaUsageProviderInterface`. Каждый модуль реализует провайдер в своём `Infrastructure/Media/` и регистрируется по тегу `app.media.usage_provider`:

| Источник                                                  | Тип ссылки                           | Ссылка в админке            |
| --------------------------------------------------------- | ------------------------------------ | --------------------------- |
| `content_pages.og_image`, `json_ld`                       | `page_seo` (OG-изображение, JSON-LD) | `/admin/pages/{id}`         |
| `content_page_blocks.content`, `settings`                 | `page_block`                         | `/admin/pages/{id}/builder` |
| `catalog_products` (`og_image`, `summary`, `description`) | `product`                            | —                           |
| `catalog_categories.description`                          | `category`                           | —                           |
| `menu_items.url`                                          | `menu_item`                          | —                           |
| `settings.setting_value`                                  | `setting`                            | `/admin/settings`           |

Поиск идёт «на лету» (без индексной таблицы, чтобы не зависеть от путей сохранения страниц): SQL-фильтр `LIKE '%uploads%media%'` по JSON/тексту и разбор путей `MediaPathExtractor`. Файл считается используемым, если найден путь оригинала или любого из его variants (`MediaAsset::allPublicPaths()`), в том числе абсолютным URL. Удалённые страницы (`deleted_at` или статус `deleted`) не учитываются; черновики и опубликованные — учитываются, статус страницы показывается в списке. Фильтр `usage` и `usageCount` в списке используют тот же индекс (один проход по провайдерам на запрос).

## Защита удаления

`DELETE /admin/api/media/assets/{id}` для используемого файла возвращает `409` `{"error", "code": "MEDIA_IN_USE", "total", "usages": [...]}` и ничего не удаляет. Повтор с `?force=1` удаляет файл вместе с variants. В UI диалог удаления загружает список использований: если файл нигде не используется — обычное подтверждение; если используется — предупреждение, список страниц/блоков/пунктов меню со ссылками и обязательный чекбокс «Я понимаю, что файл пропадёт со страниц из списка, и хочу удалить его всё равно», после которого доступна кнопка «Удалить всё равно».

## Дедупликация

При загрузке считается `sha256` исходного файла (до оптимизации, которая переупаковывает изображение) и сохраняется в `media_assets.file_hash`. Если файл с таким хешем уже есть, новый ассет не создаётся: API отвечает `200` с существующим ассетом и `duplicate: true` (новая загрузка — `201`, `duplicate: false`). В UI строка загрузки получает статус «Уже в медиатеке». Ограничение: у файлов, загруженных до миграции `Version20261007100000`, хеша нет (`null`), поэтому дедупликация действует только для новых загрузок.

## MediaPicker

`admin/features/media/MediaPicker.tsx` — поле «значение + кнопка выбора».

```tsx
<MediaPicker
    label="Изображение для соцсетей"
    absoluteUrl // сохранить абсолютный URL (нужен для og:image)
    value={form.ogImage}
    onChange={(value, asset) => setForm({ ...form, ogImage: value })}
/>
```

- `value` — `publicPath` (например, `/uploads/media/abc.webp`) или абсолютный URL при `absoluteUrl`.
- `onChange(value, asset?)` — `asset` передаётся при выборе из медиатеки, чтобы подставить `alt`; при ручном вводе и очистке он `undefined`.
- Открывает `MediaPickerDialog` (диалог с `MediaLibrary mode="select"`): в нём доступны поиск, пагинация и загрузка новых файлов. Загруженный файл сразу выбирается в панели свойств, оттуда его можно подписать и нажать «Выбрать». Двойной клик по файлу выбирает его сразу.

Где используется:

- Редактор блоков: поля изображений в форме блока (`admin/features/page-editor/workspace/BlockFields.tsx`) используют `MediaPicker`; при выборе пустой `alt`/`imageAlt` заполняется из `alt` ассета. В списках фото (`gallery`, `portfolio`, `slider` и т. п.) кнопка добавления открывает `MediaPickerDialog`. Для блоков без ручной формы поля изображений определяются по ключам (`image`, `src`, `imageUrl`, `poster`, `backgroundImage`, …), логика — `blocks/shared/mediaFields.ts`.
- TipTap (`SimpleEditor`): кнопка «Загрузить» отправляет файл в медиатеку (`handleImageUpload` → `POST /admin/api/media/assets`, прогресс и ошибки показываются), кнопка «Медиатека» вставляет изображение из библиотеки с `alt`/`title`.
- Редактор страницы (`admin/features/page-editor`): «Изображение для соцсетей» (`ogImage`, абсолютный URL по `window.location.origin`) и изображение блока `text_image`.

## Лимиты загрузки

Серверный максимум — 10 МБ (`UploadValidator`). Для staging/production `client_max_body_size` в nginx и `upload_max_filesize` / `post_max_size` в PHP должны быть не меньше этого значения (в Docker: 64 МБ, `docker/nginx/default.conf`, `docker/php/php.ini`). Ответ nginx `413` фронтенд показывает как «Файл больше лимита сервера».

## Тесты

- PHPUnit: `tests/Functional/Media/AdminMediaApiTest.php` (список, пагинация, поиск, фильтры, загрузка, дедупликация, PATCH, «где используется», защита удаления, формат ошибок), `tests/Unit/Media/Domain/*`, `tests/Unit/Media/Application/Usage/*`.
- Vitest: `admin/features/media/*.spec.*`, `admin/modules/page-builder/blocks/shared/mediaFields.spec.ts`, `admin/features/page-editor/ContentTab.spec.tsx`.
- E2E smoke: сценарий «media library» в `tests/e2e/admin-smoke.spec.ts`.
