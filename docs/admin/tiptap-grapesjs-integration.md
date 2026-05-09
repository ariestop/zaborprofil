# Разделение Tiptap и GrapesJS

## Принцип

- **GrapesJS** — управление layout, блоками и визуальной структурой страницы.
- **Tiptap** — управление rich text внутри контентных полей.

Оба инструмента не должны объединяться в один монолитный редактор.

## Текущее состояние (Этап 2)

- Reusable rich text foundation: `assets/admin/features/rich-text/*`.
- Текущий runtime-редактор: `assets/admin/components/TiptapRichTextEditor.tsx`.
- Builder runtime-контейнер: `assets/admin/modules/page-builder/*`.
- Bridge для rich text в block settings реализован в `PageBuilderContainer`.

## Правила интеграции

1. Layout-операции (перетаскивание секций/блоков) остаются в Builder.
2. Rich text редактирование встраивается как отдельный контрол внутри block settings.
3. Стили Tiptap не должны ломать CSS builder canvas.
4. Сохранение layout и rich text должно идти через согласованные API-контракты, но раздельные payload-слои.

## Migration status (legacy -> stage2)

- GrapesJS и Tiptap больше не конкурируют за одну ответственность.
- Layout и reorder управляются только Builder runtime.
- Rich text редактируется отдельным Tiptap-компонентом и сохраняется в `content.richText` выбранного блока.

## Следующий этап

- Добавить e2e/regression сценарии на разделение editor responsibilities.
- Углубить bridge для отдельных типов block schemas (hero/faq/cta и т.д.).
