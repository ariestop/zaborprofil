# Архитектура admin-shell (React SPA внутри Symfony)

## Контекст

Админка в проекте работает по гибридной модели:

- Symfony управляет `login/logout`, session-auth и backend security.
- Twig-шаблон рендерит shell-контейнер `#admin-app`.
- React + TypeScript запускается внутри этого контейнера и ведёт SPA-навигацию.
- JSON API остаётся в том же приложении и домене под `/admin/api/*`.

## Слои admin frontend

`assets/admin/` разделён на слои:

- `app/` — bootstrap, providers, global runtime.
- `routes/` — SPA routing и route config.
- `layouts/` — shell layout (topbar/sidebar/breadcrumbs/main).
- `pages/` — route-level страницы.
- `modules/` — крупные функциональные foundation-блоки (например, page-builder).
- `features/` — прикладные UI-фичи (например, rich-text).
- `entities/` — типы/модели сущностей frontend.
- `widgets/` — крупные самостоятельные виджеты shell.
- `shared/` — UI-kit, API-клиент, hooks, utils, config.

## Shell responsibilities

Shell отвечает за:

- каркас интерфейса (sidebar/topbar/breadcrumbs);
- маршрутизацию и lazy loading страниц;
- глобальные системные паттерны (error boundary, loading fallback, toasts, dialogs);
- foundation для command palette, global search и dark/light mode.

## Symfony integration points

- Twig shell template: `templates/admin/dashboard.html.twig`.
- SPA fallback routes: `src/Module/Admin/UI/Admin/DashboardController.php`.
- Security/session/access control: `config/packages/security.yaml`.
- CSRF для mutating API-запросов: `src/Module/Admin/Infrastructure/Http/AdminApiCsrfSubscriber.php`.

## Статус Этапа 2

Реализовано в текущем этапе:

- GrapesJS runtime для `PageBuilder`;
- TanStack Table для страниц/пользователей/CRM;
- dnd-kit для reorder блоков в builder;
- Recharts виджет на dashboard;
- доменные API hooks в `entities/*`.

Остаётся на следующий шаг:

- расширение e2e/регрессионных тестов builder+editors;
- полный вывод из эксплуатации legacy `views/*` и старых component flows;
- расширение аналитики dashboard и server-side фильтров таблиц.
