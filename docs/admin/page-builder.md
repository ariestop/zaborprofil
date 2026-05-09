# Page Builder runtime (Этап 2)

## Область ответственности

Page Builder отвечает за:

- layout страницы;
- секции и блоки;
- drag & drop;
- стили и responsive структуру.

## Что реализовано

Файлы:

- `assets/admin/modules/page-builder/types.ts`
- `assets/admin/modules/page-builder/hooks/useBuilderDraft.ts`
- `assets/admin/modules/page-builder/hooks/useBuilderAutosave.ts`
- `assets/admin/modules/page-builder/components/PageBuilderContainer.tsx`
- `assets/admin/pages/PageBuilderPage.tsx`

Реализованы:

- runtime-интеграция GrapesJS в `PageBuilderContainer`;
- real storage flow через `/admin/api/content/pages/{id}` и block endpoints;
- autosave/load/save foundation через `BuilderStorageAdapter`;
- preview link через `/admin/api/content/pages/{id}/preview-link`;
- versioning list через revisions endpoint;
- DnD reorder блоков с backend синхронизацией (`/blocks/reorder`);
- route `/admin/pages/:id/builder`.

## Migration status (legacy -> stage2)

- `PageBuilderPage` перешёл с in-memory режима на API-backed runtime.
- Контракты builder сохранены и расширены (`BuilderSnapshot`, block item model).
- Легаси visual-editor helper не используется как основной runtime-слой.
