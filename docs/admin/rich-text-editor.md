# Rich Text Editor в Builder

## Роль TipTap

TipTap используется **только как редактор rich-text полей внутри блока**.
Он не управляет структурой layout страницы.

## Где используется

- `assets/admin/features/rich-text/RichTextEditor.tsx`
- `assets/admin/modules/page-builder/components/BlockEditorPanel.tsx`
- `assets/admin/components/TiptapRichTextEditor.tsx`

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
