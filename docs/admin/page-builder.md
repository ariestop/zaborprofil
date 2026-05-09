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

- `admin/pages/PageBuilderPage.tsx`
- `admin/modules/page-builder/types.ts`
- `admin/modules/page-builder/registry/blockCategories.ts`
- `admin/modules/page-builder/registry/blockRegistry.ts`
- `admin/modules/page-builder/utils/pageBlocks.ts`
- `admin/modules/page-builder/state/builderStore.ts`
- `admin/modules/page-builder/components/*`

## Ограничения этапа 1

- нет `custom html/js` блока;
- rich text редактируется только через TipTap-поля блока;
- визуальный preview опирается на backend-renderer и поддерживает только
  зарегистрированные типы блоков.
