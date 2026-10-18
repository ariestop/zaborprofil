# Rich Text Editor в Builder

## Роль TipTap

TipTap используется **только как редактор rich-text полей внутри блока**.
Он не управляет структурой layout страницы.

## Где используется

- `admin/features/rich-text/RichTextEditor.tsx`
- `admin/features/page-editor/workspace/BlockFields.tsx` (поля `richtext` блоков, например «Текст»)
- `admin/components/TiptapRichTextEditor.tsx`

## Возможности тулбара

Редактор предлагает только то, что пропускает санитайзер (см. ниже): заголовки H2–H4, маркированный и
нумерованный списки, цитату, жирный, курсив, подчёркивание, ссылку, верхний и нижний индекс (м², м³, H₂O)
и выравнивание. Отмена и повтор — там же.

Картинок, выделения цветом, зачёркивания, кода, горизонтальной линии, списка задач и H1 в тулбаре нет, а
соответствующие расширения TipTap не подключены: санитайзер вырезал их при сохранении, и правка молча
пропадала (раньше так терялись и загруженные из текста картинки). Изображения вставляются отдельными
блоками конструктора с выбором из медиатеки ([media-library](media-library.md)).

## Безопасность

HTML блоков `rich-text`, `text` и `seo_text` (поля `html` и `text`) очищается allowlist-санитайзером
`symfony/html-sanitizer` с профилем `app.rich_text_sanitizer` (`config/packages/html_sanitizer.yaml`):

- при сохранении — `StructuredRichTextSanitizer::sanitizeHtml()` из `StructuredBlockDocumentService::sanitizeContent()`,
  общего для конструктора и API отдельных блоков;
- при выводе — фильтр `|sanitize_html('app.rich_text_sanitizer')` в `public/blocks/rich-text.html.twig`,
  поэтому безопасен и HTML, сохранённый раньше.

Разрешены `p`, `br`, `strong`/`b`, `em`/`i`, `u`, `sup`, `sub`, списки, `h2`–`h4` (с `style` для выравнивания),
`blockquote`, `span` и ссылки `a` (`href` со схемами http/https/mailto/tel и относительные, `title`, `target`, `rel`).
Обработчики событий, `javascript:`-ссылки, `script`/`style`, изображения, `hr` и прочие теги удаляются; у обёрток
вроде `div`, `h1`, `mark`, `s`, `code` удаляется только тег, текст остаётся. Лимит входа — 500 000 байт.

Тот же набор тегов держат `StructuredRichTextSanitizer::ALLOWED_TAGS` (предварительная очистка полей) и
превью в админке (`admin/shared/lib/sanitize-html.ts`): меняйте их вместе с YAML и тулбаром.
Проверки: `tests/Functional/Content/RichTextEditorMarkupTest.php`, `simple-editor.spec.tsx`.

Остальные текстовые поля блоков шаблоны выводят с экранированием Twig; для них сохранена прежняя очистка тегов
(`sanitizeText()`), HtmlSanitizer к ним не применяется, чтобы `&` и кавычки не превращались в сущности.

## Ограничения этапа 1

- HTML-режим произвольного блока не поддерживается;
- rich-text хранится в `content` (`html`/`text`) конкретного блока;
- финальный HTML выводится через контролируемые Twig partials.
