# Structured Visual CMS Builder

## Зачем это сделано

Builder переведен на **structured block model**:

- без GrapesJS и без свободной HTML-верстки страницы;
- с управляемым каталогом блоков;
- с централизованной backend-валидацией структуры.

## Базовый UX

Builder — вкладка «Контент и блоки» [единого редактора страницы](page-editor.md): `/admin/pages/:id`
(старый URL `/admin/pages/:id/builder` продолжает работать и открывает ту же вкладку).

Как открыть: список страниц `/admin/pages` → выбрать страницу.

Доступные операции:

- добавить блок из каталога;
- выбрать блок и редактировать его `content/settings`;
- дублировать/удалить/включить/выключить блок;
- отсортировать блоки через drag & drop;
- сохранить черновик (`PUT /builder`) — кнопкой «Сохранить» в шапке редактора или автосохранением;
- посмотреть быстрый preview блоков (`POST /builder/preview`);
- опубликовать страницу — кнопкой «Опубликовать» в шапке редактора.

## JSON-режим

Builder — единственный интерфейс редактирования блоков (legacy-редактор в `/admin/pages` удалён).
JSON-панель блока показывается в «Расширенном режиме» (переключатель в шапке редактора)
и всегда — для блоков без собственной формы. Пресеты слайдера доступны в `SliderEditor`.

## Ключевые frontend-модули

- `admin/features/page-editor/*` (экран, вкладки, сохранение)
- `admin/pages/PageEditorPage.tsx`
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
