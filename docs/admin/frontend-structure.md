# Frontend-структура админки

## Цель структуры

Структура `assets/admin/` должна масштабироваться под модули CMS, не превращаясь в набор хаотичных компонентов.

## Текущая структура foundation

```text
assets/admin/
  app/
  routes/
  layouts/
  pages/
  modules/
  features/
  entities/
  widgets/
  shared/
    ui/
    api/
    lib/
    hooks/
    config/
```

## Правила

- Бизнес-логика не должна находиться внутри `shared/ui`.
- Route-level загрузка данных выполняется через `shared/api` + TanStack Query.
- В `pages/` хранится композиция экранов, а не низкоуровневые примитивы.
- Повторно используемые доменные части выносятся в `features/` и `modules/`.
- Большие интеграции (builder, media, crm) оформляются как отдельные `modules/*`.

## Совместимость

Легаси-файлы старой структуры (`views/`, `components/`) сохраняются на переходный период.
Новые реализации должны идти через новую структуру и постепенно вытеснять старые части без больших одномоментных миграций.

## Migration status (Этап 2)

- `pages/*` теперь содержат реальные API-backed экраны для:
  - `Pages` (TanStack Table);
  - `Users` (data-grid + role update form);
  - `CRM` (leads grid + status mutations);
  - `PageBuilder` (GrapesJS runtime + DnD + bridge).
- Легаси `views/*`, `router/index.ts` и `components/AdminShell.tsx` удалены.
- Основной runtime-путь админки: `app/AdminApp.tsx` + `routes/index.tsx` + `layouts/AdminShellLayout.tsx`.
