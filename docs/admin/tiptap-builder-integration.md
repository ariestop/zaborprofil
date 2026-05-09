# Разделение Tiptap и Builder Snapshot

## Принцип

- **Builder Snapshot** — управление layout через `content.html` и `content.css` блока `builder_canvas`.
- **Tiptap** — управление rich text внутри контентных полей блоков.

Инструменты выполняют разные роли и не смешиваются в один монолитный редактор.

## Текущее состояние

- Reusable rich text foundation: `assets/admin/features/rich-text/*`.
- Builder runtime-контейнер: `assets/admin/modules/page-builder/*`.
- Bridge для rich text в block settings реализован в `PageBuilderContainer`.

## Правила интеграции

1. Layout-операции и snapshot-редактирование остаются в Builder.
2. Rich text редактирование встраивается как отдельный контрол внутри block settings.
3. Стили Tiptap не должны ломать CSS builder canvas.
4. Сохранение layout и rich text идёт через согласованные API-контракты, но раздельные payload-слои.

## Migration status

- Builder snapshot и Tiptap не конкурируют за одну ответственность.
- Layout и reorder управляются Page Builder runtime.
- Rich text редактируется отдельным Tiptap-компонентом и сохраняется в `content.richText` выбранного блока.
