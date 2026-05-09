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

Легаси-файлы старой структуры уже выведены из эксплуатации.
Новые реализации добавляются только в слои новой структуры (`app/routes/layouts/pages/modules/features/entities/widgets/shared`).

## Migration status (Этап 3)

- `pages/*` теперь содержат реальные API-backed экраны для:
  - `Pages` (TanStack Table);
  - `Users` (data-grid + role update form);
  - `CRM` (leads grid + status mutations);
  - `PageBuilder` (snapshot HTML/CSS + DnD + bridge).
- Легаси `views/*`, `router/index.ts` и `components/AdminShell.tsx` удалены.
- Основной runtime-путь админки: `app/AdminApp.tsx` + `routes/index.tsx` + `layouts/AdminShellLayout.tsx`.
- Добавлен hardened prefetch-слой:
  - idle-first prefetch для вероятных переходов (`routes/prefetch.ts`);
  - bounded concurrency (ограничение параллельных prefetch задач);
  - network/device-aware деградация (slow network/save-data/low-end устройство -> более консервативный prefetch);
  - hover/focus prefetch на sidebar навигации.

## Quality gates для frontend слоя

- Обязательный `npm run typecheck`.
- Обязательный `npm run test:frontend`.
- Scoped lint для admin runtime: `npm run lint:admin`.
- Build guardrail по размерам критичных чанков: `npm run check:chunks`.
