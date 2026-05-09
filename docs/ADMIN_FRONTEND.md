# React-админка

Админка остается защищенной Symfony Security, а пользовательский интерфейс
загружается как React + TypeScript приложение внутри Twig-шаблона `templates/admin/dashboard.html.twig`.

Точка входа фронтенда: `admin/app.ts`.

## Маршрутизация

- `/admin/login` остается серверной Twig-страницей.
- `/admin`, `/admin/dashboard`, `/admin/settings`, `/admin/seo/redirects` рендерят один SPA shell.
- `/admin/api/*` остается JSON API и защищается CSRF subscriber.

Используется `react-router-dom` для клиентской навигации и `zustand` для
базового состояния текущего администратора (`admin/stores/auth.ts`).

Route-level страницы находятся в `admin/pages/*.tsx`,
layout/navigation — в `admin/layouts/`,
общие UI-элементы и API слой — в `admin/shared/`.

## API-клиент

`admin/shared/api/client.ts` автоматически добавляет:

- `X-CSRF-Token` для state-changing запросов;
- `X-Requested-With: XMLHttpRequest`;
- `X-Request-Id`;
- `credentials: same-origin`.

CSRF-токен передается из Twig через meta-теги.

## Редактор контента

Рич-текст редактор реализован через TipTap (`admin/components/TiptapRichTextEditor.tsx`).
Шаблоны вставок и кнопки тулбара вынесены в отдельные модули:

- `admin/components/tiptap-templates/`
- `admin/components/useTiptapToolbar.ts`
- `admin/components/hooks/`

Для регрессий редактора используется Vitest-спека
`admin/components/TiptapRichTextEditor.spec.ts`.

## Structured Page Builder

Page Builder реализован как **Structured Visual CMS Builder** и не использует GrapesJS.

Ключевые принципы:

- каталог готовых блоков по категориям;
- редактирование блока через формы/JSON панели;
- drag & drop сортировка через `dnd-kit`;
- dirty state, autosave foundation, unsaved changes guard;
- preview и publish через backend builder endpoints.

Основные frontend-модули:

- `admin/modules/page-builder/types.ts`
- `admin/modules/page-builder/registry/*`
- `admin/modules/page-builder/utils/pageBlocks.ts`
- `admin/modules/page-builder/state/builderStore.ts`
- `admin/modules/page-builder/components/*`
- `admin/pages/PageBuilderPage.tsx`

## Сборка и проверки

Для локальной разработки и CI используйте make-цели:

- `make npm-install` — установка npm-зависимостей в docker compose;
- `make npm-build` — production-сборка Vite;
- `make npm-dev` — dev server Vite.

Не запускайте `npm install`/`npm run build` в app-контейнере от root,
чтобы не создавать root-owned артефакты в `public_html/build/`.
