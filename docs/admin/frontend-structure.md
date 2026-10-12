# Frontend-структура админки

## Цель структуры

Структура `admin/` должна масштабироваться под модули CMS, не превращаясь в набор хаотичных компонентов.

## Текущая структура foundation

```text
admin/
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
- Build guardrail по размерам критичных чанков: `npm run check:chunks` (`tools/perf/check-admin-chunks.mjs`, базовая линия — `tools/perf/admin-chunk-baseline.json`).

### Чанки и начальная загрузка

- `admin-initial` — весь JS, который грузится при открытии любой страницы админки: entry `admin/app.ts` и его статические импорты, без ленивых маршрутов. Бюджет 520 КБ (сейчас ~457 КБ: entry ~231 КБ, `vendor-react` ~190 КБ, `vendor-query` ~34 КБ).
- Рантайм React (`react`, `react-dom`, `scheduler`, `use-sync-external-store`) вынесен в `vendor-react` явно (`manualChunks` в `vite.config.ts`). Без этого Rollup клал его в первый vendor-чанк, которому он понадобился, и entry статически тянул `vendor-tiptap` + `vendor-prosemirror` (~411 КБ редактора) на каждой странице.
- Редактор (`vendor-tiptap`, `vendor-prosemirror`, `RichTextEditor`) грузится только с ленивыми маршрутами. Если `admin-initial` вырос скачком — проверить статические импорты entry в `public_html/build/.vite/manifest.json`.
- `vite_entry_script_tags()` выводит `<link rel="modulepreload">` для статических импортов entry, чтобы они грузились параллельно с ним, а не после его разбора.
