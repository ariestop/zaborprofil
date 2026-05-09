# React-админка

Админка остается защищенной Symfony Security, а пользовательский интерфейс
загружается как React + TypeScript приложение внутри Twig-шаблона `templates/admin/dashboard.html.twig`.

Точка входа фронтенда: `assets/admin/app.ts`.

## Маршрутизация

- `/admin/login` остается серверной Twig-страницей.
- `/admin`, `/admin/dashboard`, `/admin/settings`, `/admin/seo/redirects` рендерят один SPA shell.
- `/admin/api/*` остается JSON API и защищается CSRF subscriber.

Используется `react-router-dom` для клиентской навигации и `zustand` для
базового состояния текущего администратора (`assets/admin/stores/auth.ts`).

Route-level страницы находятся в `assets/admin/pages/*.tsx`,
layout/navigation — в `assets/admin/layouts/`,
общие UI-элементы и API слой — в `assets/admin/shared/`.

## API-клиент

`assets/admin/api/client.ts` автоматически добавляет:

- `X-CSRF-Token` для state-changing запросов;
- `X-Requested-With: XMLHttpRequest`;
- `X-Request-Id`;
- `credentials: same-origin`.

CSRF-токен передается из Twig через meta-теги.

## Редактор контента

Рич-текст редактор реализован через TipTap (`assets/admin/components/TiptapRichTextEditor.tsx`).
Шаблоны вставок и кнопки тулбара вынесены в отдельные модули:

- `assets/admin/components/tiptap-templates/`
- `assets/admin/components/useTiptapToolbar.ts`
- `assets/admin/components/hooks/`

Для регрессий редактора используется Vitest-спека
`assets/admin/components/TiptapRichTextEditor.spec.ts`.

## Сборка и проверки

Для локальной разработки и CI используйте make-цели:

- `make npm-install` — установка npm-зависимостей в docker compose;
- `make npm-build` — production-сборка Vite;
- `make npm-dev` — dev server Vite.

Не запускайте `npm install`/`npm run build` в app-контейнере от root,
чтобы не создавать root-owned артефакты в `public_html/build/`.
