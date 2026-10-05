# Rich Text Editor в Builder

## Роль TipTap

TipTap используется **только как редактор rich-text полей внутри блока**.
Он не управляет структурой layout страницы.

## Где используется

- `admin/features/rich-text/RichTextEditor.tsx`
- `admin/features/page-editor/workspace/BlockFields.tsx` (поля `richtext` блоков, например «Текст»)
- `admin/components/TiptapRichTextEditor.tsx`

## Изображения

Кнопка «Загрузить» в тулбаре загружает файл через `POST /admin/api/media/assets` (`handleImageUpload` в `admin/components/lib/tiptap-utils.ts`) и вставляет `publicPath`; кнопка «Медиатека» открывает `MediaPickerDialog` и вставляет выбранное изображение с `alt`/`title`. Подробнее: [media-library](media-library.md).

## Безопасность

На backend rich-text проходит sanitize-процедуру через
`StructuredRichTextSanitizer`:

- удаляются `<script>` теги;
- удаляются inline-обработчики (`on*="..."`).

Это не заменяет полноценный allowlist sanitizer, но закрывает базовый риск XSS
на первом этапе Structured Builder.

## Ограничения этапа 1

- HTML-режим произвольного блока не поддерживается;
- rich-text хранится в `content` (`html`/`text`) конкретного блока;
- финальный HTML выводится через контролируемые Twig partials.
