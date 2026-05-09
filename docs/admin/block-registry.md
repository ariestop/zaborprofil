# Block Registry

## Назначение

`Block Registry` описывает поддерживаемые типы блоков, категории и дефолтные
данные для создания новых экземпляров в builder.

## Где находится

- `assets/admin/modules/page-builder/registry/blockCategories.ts`
- `assets/admin/modules/page-builder/registry/blockRegistry.ts`
- `assets/admin/modules/page-builder/blocks/index.ts`

## Что хранится в registry

Для каждого `block.type`:

- `title` и `description`;
- `category`;
- `defaults.content` и `defaults.settings`;
- `contentSchema` и `settingsSchema` (Zod).

## Как добавить новый блок

1. Добавить новый `type` в `assets/admin/modules/page-builder/types.ts`.
2. Добавить описание блока в `blockRegistry.ts`.
3. Проверить, что `defaults` и Zod-схема валидны.
4. Добавить/обновить Twig renderer для публичного SSR (если блок публичный).
5. Добавить тесты на `createBlock`, `normalizePageBlocks` и валидацию.

## Почему без GrapesJS

Проекту нужен предсказуемый SEO-friendly output и строгий контроль структуры
контента. Registry-модель позволяет:

- запретить произвольные block types;
- валидировать и санитизировать поля на backend;
- поддерживать стабильный SSR-маркап для каждого типа блока.
