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

## System Center foundation

В admin-shell добавлен модуль `System Center` (`/admin/system/*`) с 10 подразделами:

1. Обзор системы
2. Процессы и сервисы
3. Логи
4. Очереди Symfony Messenger
5. Кэш
6. База данных
7. Безопасность
8. Бэкапы
9. Деплой
10. Аудит действий админов

Backend API реализован под `/admin/api/system/*` через отдельные контроллеры `System*Controller` и сервисы `*Service`.

Ключевые ограничения:

- frontend не передаёт произвольные shell-команды;
- опасные действия выполняются только через backend whitelist-команд;
- все mutating-запросы требуют CSRF + Origin;
- для dangerous действий обязателен одноразовый `confirmToken` (TTL + actor/action binding);
- dangerous actions доступны только `ROLE_SUPER_ADMIN` (`system.dangerous`);
- каждое системное действие логируется в audit log (attempt/success/failure).

## Symfony integration points

- Twig shell template: `templates/admin/dashboard.html.twig`.
- SPA fallback routes: `src/Module/Admin/UI/Admin/DashboardController.php`.
- Security/session/access control: `config/packages/security.yaml`.
- CSRF для mutating API-запросов: `src/Module/Admin/Infrastructure/Http/AdminApiCsrfSubscriber.php`.

## Статус Этапа 3 (stabilization)

Реализовано:

- Snapshot runtime для `PageBuilder`;
- TanStack Table для страниц/пользователей/CRM;
- dnd-kit для reorder блоков в builder;
- Recharts виджет на dashboard;
- доменные API hooks в `entities/*`.
- Functional regression для admin API edge-cases: 401/403/404/422 + csrf/origin/session.
- Regression flow-тест editor-пути: pages -> detail -> builder -> reorder -> rich-text -> preview.
- Усиленный CI frontend gate: отдельные `typecheck`, `test:frontend`, `lint:admin`, chunk budgets.
- Prefetch policy с network/device-aware деградацией и bounded concurrency.

## Release readiness checklist

- [ ] Backend CI зелёный (`composer check:*`, migrations, schema validate, phpunit, smoke).
- [ ] Frontend CI зелёный (`npm run typecheck`, `npm run test:frontend`, `npm run lint:admin`, `npm run build`, `npm run check:chunks`).
- [ ] Ручная проверка критичных путей: login, pages list/detail, builder save/reorder/rich-text, preview.
- [ ] Legacy-ссылки на `views/*`/старый router отсутствуют в коде и docs.
- [ ] Известные ограничения и rollback-план актуализированы перед merge.

## Known limitations

- Browser smoke E2E на Playwright подключён только для критичного admin-flow; расширенные editor regression кейсы пока остаются в ручных чек-листах `tests/E2E`.
- Dashboard analytics пока закрывает только базовый виджет; расширенная бизнес-аналитика запланирована отдельно.
