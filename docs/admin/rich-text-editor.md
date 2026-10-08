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

HTML блоков `rich-text`, `text` и `seo_text` (поля `html` и `text`) очищается allowlist-санитайзером
`symfony/html-sanitizer` с профилем `app.rich_text_sanitizer` (`config/packages/html_sanitizer.yaml`):

- при сохранении — `StructuredRichTextSanitizer::sanitizeHtml()` из `StructuredBlockDocumentService::sanitizeContent()`,
  общего для конструктора и API отдельных блоков;
- при выводе — фильтр `|sanitize_html('app.rich_text_sanitizer')` в `public/blocks/rich-text.html.twig`,
  поэтому безопасен и HTML, сохранённый раньше.

Разрешены `p`, `br`, `strong`/`b`, `em`/`i`, `u`, списки, `h2`–`h4` (с `style` для выравнивания), `blockquote`,
`span` и ссылки `a` (`href` со схемами http/https/mailto/tel и относительные, `title`, `target`, `rel`).
Обработчики событий, `javascript:`-ссылки, `script`/`style`, изображения и прочие теги удаляются; у обёрток
вроде `div`, `h1`, `mark` удаляется только тег, текст остаётся. Лимит входа — 500 000 байт.

Остальные текстовые поля блоков шаблоны выводят с экранированием Twig; для них сохранена прежняя очистка тегов
(`sanitizeText()`), HtmlSanitizer к ним не применяется, чтобы `&` и кавычки не превращались в сущности.

## Ограничения этапа 1

- HTML-режим произвольного блока не поддерживается;
- rich-text хранится в `content` (`html`/`text`) конкретного блока;
- финальный HTML выводится через контролируемые Twig partials.
