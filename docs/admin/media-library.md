# Медиатека и MediaPicker

Раздел `/admin/media` — рабочая медиатека поверх Admin API `/admin/api/media/assets`. Тот же компонент выбора файла (`MediaPicker`) используется в блоках Page Builder, в TipTap и в полях изображений страницы.

## Admin API

| Метод    | URL                            | Право          | Описание                                                                                            |
| -------- | ------------------------------ | -------------- | --------------------------------------------------------------------------------------------------- |
| `GET`    | `/admin/api/media/assets`      | `media.upload` | Список с пагинацией, поиском, фильтром и сортировкой                                                |
| `POST`   | `/admin/api/media/assets`      | `media.upload` | Загрузка файла (`multipart/form-data`, поле `file`)                                                 |
| `PATCH`  | `/admin/api/media/assets/{id}` | `media.upload` | Изменение `alt` и `title` (`{"alt": "...", "title": "..."}`; пустая строка или `null` очищают поле) |
| `DELETE` | `/admin/api/media/assets/{id}` | `media.delete` | Удаление записи и файлов (оригинал + variants)                                                      |

Параметры `GET`:

| Параметр  | Значения                                                        | По умолчанию |
| --------- | --------------------------------------------------------------- | ------------ |
| `page`    | целое `>= 1`                                                    | `1`          |
| `perPage` | `1..100`                                                        | `24`         |
| `q`       | подстрока в `originalName`, `alt` или `title` (до 100 символов) | пусто        |
| `type`    | `image` (MIME `image/*`) или `document` (остальное, сейчас PDF) | все файлы    |
| `sort`    | `newest`, `oldest`, `name`, `size`                              | `newest`     |

Ответ: `{"assets": [...], "pagination": {"page", "perPage", "total", "totalPages"}}`. Элемент `assets` содержит `id`, `originalName`, `filename`, `publicPath`, `mimeType`, `size`, `width`, `height`, `variants`, `alt`, `title`, `createdAt`. Некорректные параметры дают `422` `{"error", "code": "VALIDATION"}`.

Ошибки загрузки и изменения имеют единый формат `{error, code}` (`AdminApiErrorResponder`, см. [12-admin-area](../12-admin-area.md)): нет поля `file` — `400 BAD_REQUEST`, файл больше лимита PHP — `422 FILE_TOO_LARGE`, недопустимый тип/размер/размеры — `422 VALIDATION`, неизвестный `id` — `404 NOT_FOUND`.

Поля `alt` и `title` хранятся в `media_assets.alt` и `media_assets.title` (nullable, до 255 символов; миграция `Version20261006090000`).

## Страница `/admin/media`

Реализация: `admin/pages/MediaPage.tsx` + `admin/features/media/MediaLibrary.tsx`.

- Сетка или список (переключатель «Сетка» / «Список»).
- Поиск (debounce 300 мс), фильтр «Все файлы / Изображения / Документы», сортировка, серверная пагинация.
- Загрузка: кнопка «Загрузить файлы» или перетаскивание файлов на область медиатеки. Мультизагрузка с очередью (2 файла параллельно), прогресс по каждому файлу (XHR `upload.onprogress`), ошибки по файлам с русскими сообщениями, «Повторить» и «Отменить».
- Перед отправкой файл проверяется на клиенте (JPG, PNG, WebP, AVIF, PDF; до 10 МБ); серверная проверка (`UploadValidator`) остаётся обязательной.
- Панель свойств выбранного файла: предпросмотр, размер, габариты, URL (копирование), редактирование `alt` и `title`, удаление.
- Удаление требует подтверждения (`ConfirmDialog`). Проверки «файл используется» пока нет — это пункт C2 из плана улучшений.

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

- Page Builder: `BlockMediaFields` в `BlockEditorPanel` находит в `content` блока поля изображений (`image`, `src`, `imageUrl`, `poster`, `backgroundImage`, для `before-after` — `before`/`after`, в том числе внутри `items[]`) и показывает для каждого `MediaPicker`. При выборе пустой `alt`/`imageAlt` заполняется из `alt` ассета. Для `gallery`, `works-gallery`, `slider`, `portfolio`, `fence-types` есть кнопка «Добавить изображение из медиатеки». Логика — `blocks/shared/mediaFields.ts`.
- TipTap (`SimpleEditor`): кнопка «Загрузить» отправляет файл в медиатеку (`handleImageUpload` → `POST /admin/api/media/assets`, прогресс и ошибки показываются), кнопка «Медиатека» вставляет изображение из библиотеки с `alt`/`title`.
- Страницы (`ContentPagesView`): «Изображение для соцсетей» (`ogImage`, абсолютный URL по `window.location.origin`) и изображение блока `text_image`.

## Лимиты загрузки

Серверный максимум — 10 МБ (`UploadValidator`). Для staging/production `client_max_body_size` в nginx и `upload_max_filesize` / `post_max_size` в PHP должны быть не меньше этого значения (в Docker: 64 МБ, `docker/nginx/default.conf`, `docker/php/php.ini`). Ответ nginx `413` фронтенд показывает как «Файл больше лимита сервера».

## Тесты

- PHPUnit: `tests/Functional/Media/AdminMediaApiTest.php` (список, пагинация, поиск, фильтры, загрузка, PATCH, удаление, формат ошибок), `tests/Unit/Media/Domain/*`.
- Vitest: `admin/features/media/*.spec.*`, `admin/modules/page-builder/blocks/shared/mediaFields.spec.ts`, `admin/modules/page-builder/components/BlockMediaFields.spec.tsx`.
- E2E smoke: сценарий «media library» в `tests/e2e/admin-smoke.spec.ts`.
