# Structured Visual CMS Builder

## Зачем это сделано

Builder переведен на **structured block model**:

- без GrapesJS и без свободной HTML-верстки страницы;
- с управляемым каталогом блоков;
- с централизованной backend-валидацией структуры.

## Базовый UX

Путь: `/admin/pages/:id/builder`.

Доступные операции:

- добавить блок из каталога;
- выбрать блок и редактировать его `content/settings`;
- дублировать/удалить/включить/выключить блок;
- отсортировать блоки через drag & drop;
- сохранить черновик (`PUT /builder`);
- посмотреть preview (`POST /builder/preview`);
- опубликовать (`POST /builder/publish`).

## Ключевые frontend-модули

- `assets/admin/pages/PageBuilderPage.tsx`
- `assets/admin/modules/page-builder/types.ts`
- `assets/admin/modules/page-builder/registry/blockCategories.ts`
- `assets/admin/modules/page-builder/registry/blockRegistry.ts`
- `assets/admin/modules/page-builder/utils/pageBlocks.ts`
- `assets/admin/modules/page-builder/state/builderStore.ts`
- `assets/admin/modules/page-builder/components/*`

## Ограничения этапа 1

- нет `custom html/js` блока;
- rich text редактируется только через TipTap-поля блока;
- визуальный preview опирается на backend-renderer и поддерживает только
  зарегистрированные типы блоков.
