# Structured Visual CMS Builder

## Зачем это сделано

Builder переведен на **structured block model**:

- без GrapesJS и без свободной HTML-верстки страницы;
- с управляемым каталогом блоков;
- с централизованной backend-валидацией структуры.

## Базовый UX

Путь: `/admin/pages/:id/builder`.

Как открыть:

1. Перейти в список страниц `/admin/pages`.
2. Выбрать нужную страницу.
3. Нажать `Открыть Builder` в списке или перейти по прямому URL `/admin/pages/:id/builder`.

Важно: экран `/admin/pages` содержит legacy-редактор страниц/блоков (JSON-first),
а `Page Builder` — отдельный экран структурированного редактирования.

Доступные операции:

- добавить блок из каталога;
- выбрать блок и редактировать его `content/settings`;
- дублировать/удалить/включить/выключить блок;
- отсортировать блоки через drag & drop;
- сохранить черновик (`PUT /builder`);
- посмотреть preview (`POST /builder/preview`);
- опубликовать (`POST /builder/publish`).

## Когда использовать Page Builder, а когда legacy

- `Page Builder` — основной режим для контент-редактирования (структурные блоки, меньше ручного JSON, удобнее для операторов).
- Legacy-редактор в `/admin/pages` — экспертный/fallback режим для точечных правок JSON и низкоуровневой диагностики payload.
- Для блока `slider` пресеты доступны в обоих интерфейсах:
  - в `Page Builder` через `admin/modules/page-builder/blocks/slider/SliderEditor.tsx`;
  - в legacy-модалке блока (`/admin/pages`) через секцию `Пресеты слайдера`.

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
