# ADR-0012: Admin = SSR-shell + Vue 3 SPA-island, без полного SPA и без JWT

## Статус

Accepted, 2026.

## Контекст

Админка `/admin` сейчас находится в гибридном состоянии:

- Логин — Symfony `form_login` + `templates/admin/security/login.html.twig` (классический SSR + CSRF).
- `/admin/*` (после логина) — `DashboardController` рендерит `templates/admin/dashboard.html.twig`, который уже подгружает Vue entry `assets/admin/main.ts` через `vite_entry_*`.
- API — `/admin/api/content/...`, `/admin/api/settings/...`, `/admin/api/seo/...` (JSON), защищены:
  - `AdminApiCsrfSubscriber` (double-submit CSRF: meta-токен `<meta name="admin-csrf-token">` + заголовок `X-CSRF-Token`),
  - `AdminApiOriginSubscriber` (same-origin),
  - `AdminPermissionVoter` (RBAC).
- Cookie-сессия — нативные PHP файлы (см. [ADR-0007](0007-redis-cache-and-messenger.md), [20-security-and-access-control.md](../20-security-and-access-control.md#session)).
- Тест `tests/Functional/Admin/AdminSpaShellTest.php` уже проверяет, что shell отдаётся.

Стек уже включает Vue 3.5, Vite 7, Tailwind CSS, vue-tsc, TypeScript 5.8. Возможные направления развития:

1. **Pure SPA** — `/admin` отдаёт только index.html-shell, всё остальное — `/admin/api/*`. Auth через JWT/refresh.
2. **Pure Twig admin** — отказ от Vue, возврат к серверному рендерингу всех админ-страниц.
3. **Hybrid SSR-shell + SPA-island** — Twig отдаёт тонкий shell с Vue-маунтингом, всё остальное — JSON API. Auth остаётся cookie-сессией.

## Проблема

Без явного решения растёт риск:

- двойной разработки (то Twig-страница, то Vue-страница для одного и того же экрана);
- конфликта между сессионной auth и токенной auth;
- неконсистентного UX (часть админки SPA, часть — page reload);
- утяжеления стека JWT/refresh-инфраструктурой ради сомнительной выгоды;
- неподготовленности к SaaS-режиму (если когда-то понадобится публичный API).

Нужно зафиксировать паттерн на 2026–2027 и привести существующий код к нему.

## Решение

Использовать **Hybrid SSR-shell + Vue 3 SPA-island** как канонический паттерн админки.

```mermaid
flowchart LR
    User[Пользователь] -->|GET /admin/login| LoginC[SecurityController<br/>Twig + form_login]
    LoginC -->|POST credentials| FW[Symfony Firewall main]
    FW -->|set session cookie| Cookie[(zaborprofil_session)]
    Cookie --> Shell[GET /admin/...<br/>DashboardController]
    Shell -->|Twig render| ShellHTML[admin/dashboard.html.twig<br/>div#admin-app + vite_entry_link_tags]
    ShellHTML -->|monte| Vue[Vue 3 SPA<br/>assets/admin/main.ts]
    Vue -->|vue-router /admin/*| VueViews[CRUD страниц / блоков / SEO / settings]
    VueViews -->|fetch JSON + X-CSRF-Token| API[/admin/api/*<br/>JsonRequest + Voter]
    API -->|cookie session + CSRF + voter| Handler[Application Handler]
```

**Конкретно:**

1. `/admin/login`, `/admin/logout` — остаются SSR Twig-страницами под Symfony `form_login` (никаких JWT, никаких токенных рефрешей).
2. `/admin/*` (всё после логина) — `DashboardController` (или один shell-controller) отдаёт минимальный Twig-shell:
   - `<div id="admin-app"></div>`,
   - `vite_entry_link_tags('assets/admin/main.ts')` + соответствующие `script_tags`,
   - `<meta name="admin-csrf-token" content="{{ csrf_token('admin_api') }}">`,
   - `<meta name="admin-base-url" content="{{ url('admin_dashboard') }}">`,
   - `noindex` через `AdminNoIndexSubscriber` (уже есть).
3. Внутри shell стартует Vue 3 SPA с `vue-router` (`createWebHistory('/admin')`), Pinia (или `ref`-storage на старте), TypeScript.
4. Все данные читаются/пишутся через `/admin/api/*` (JSON). На клиенте — единый fetch-wrapper, который:
   - читает CSRF из meta-тега и шлёт `X-CSRF-Token`,
   - добавляет `credentials: 'include'`,
   - на 401 редиректит на `/admin/login` (browser navigation).
5. Auth остаётся cookie-сессией. Никаких JWT/refresh tokens.
6. Same-origin строго: админка живёт на том же домене, что и публичный сайт. Никаких поддоменов.

## Причины

### Почему SSR-shell + SPA-island

- **Простота auth.** Cookie-сессия + CSRF — проверенный, безопасный, поддерживаемый Symfony из коробки. Не нужно строить JWT/refresh/blacklist/rotation.
- **Безопасность.** CSRF + same-origin + voters — те же примитивы, что Symfony использует везде. SPA-only обычно требует выводить токен наружу, обрабатывать XSS-кражу токена, ротацию и т.д.
- **Одна точка входа.** `/admin/login` — обычная страница, можно открыть из любого письма / закладки.
- **Постепенная миграция.** Можно поэкранно переносить серверные admin-страницы в Vue, не ломая логин и не переписывая sec-инфраструктуру.
- **SEO нерелевантно.** `/admin/*` под `noindex,nofollow` — не нужен SSR Vue, гидратация, hydration mismatch и пр.
- **Стек уже под это.** Vite + Vue 3 + Tailwind + TS уже в `package.json`, `vite_entry_link_tags` уже работает, CSRF subscriber уже есть, тест shell-pattern уже есть. Это путь наименьшего сопротивления.
- **Использование существующего API.** `/admin/api/*` уже спроектированы как JSON; SSR-shell не требует переписывать их.

### Почему НЕ Pure SPA

- JWT/refresh-инфраструктура — отдельная сложность без выгоды (мы не строим мобильный клиент сегодня).
- Логин-страница всё равно должна где-то жить; если это Vue route — потребуется отдельная схема выдачи initial auth state.
- Любая компрометация admin JS = exfiltration JWT. Cookie + `HttpOnly` + `Secure` + `SameSite=Lax` строго безопаснее.
- Усложняет работу с CSRF (или вынуждает отказаться от него, что хуже для безопасности).
- Bookmarking уровня страниц/маршрутов всё равно работает в hybrid через `vue-router`.

### Почему НЕ Pure Twig admin

- 2026 год; редактору нужна SPA-UX (drag-and-drop блоков, инлайн-редактирование, оптимистичные обновления, мгновенный отклик без перезагрузки).
- Уже инвестировано в Vue 3 + Vite + Tailwind, откат — потеря инвестиций без выгоды.
- JSON API уже есть и должен оставаться: его можно дать мобилке/интеграциям/3rd party.
- Twig-only admin вынуждает дублировать логику валидации (Symfony Form + ручные JS-валидаторы для UX) или отказываться от UX.

## Альтернативы

| Вариант | Плюсы | Минусы | Почему отклонено |
|---|---|---|---|
| **Pure Vue 3 SPA + JWT** | Чистая архитектура; готов API для мобилок | JWT-инфра; сложный логин; CSRF/XSS-риски; refresh tokens | Не нужно сейчас; cookie-session проще и безопаснее |
| **Pure Twig admin** | Один стек; нативный Symfony Forms | Плохой UX; дублирование валидации; потеря Vue-инвестиций | UX редактора критичен |
| **Inertia.js (Twig + SPA-routing)** | SSR + SPA-навигация без отдельного API | Доп. зависимость; меньше распространена в Symfony-экосистеме; ломает чистый JSON API | Лишний слой без выгоды |
| **Livewire/Hotwire** | Без JS-фреймворка | Чужая ментальная модель; не сочетается с Vue; SSR-only | Уже выбрали Vue 3 |
| **Admin на subdomain `admin.zaborprofil.ru`** | Изоляция cookie | Раздувает CORS/CSRF/деплой; same-origin теряется; сложнее CSP | Безопасностно проигрывает same-origin |

## Компромиссы

- Двойной runtime: Twig для логина + Vue для shell-внутренностей. Ментальная нагрузка на новых разработчиков. Митигировано: логин — единственная Twig-страница админки, shell — один тонкий шаблон.
- Vue SPA при первой загрузке грузит весь bundle. Митигировано Vite code-splitting'ом и lazy-routing'ом (`defineAsyncComponent`).
- Нативная сессия в файлах не масштабируется на несколько узлов PHP-FPM. Митигировано: текущий стек — single-VPS; при горизонтальном масштабировании переходим на Redis-backed sessions (см. [20-security-and-access-control.md#session](../20-security-and-access-control.md#session)). Это отдельный ADR в будущем.
- Vue SPA не индексируется (что и нужно), но debug сложнее, чем Twig (нужны Vue DevTools). Принято.

## Риски

| Риск | Вероятность | Impact | Mitigation |
|---|---|---|---|
| XSS в админке → угон сессии | Низкая | Высокий | `HttpOnly`/`Secure`/`SameSite=Lax` cookie, CSP (целевое — enforce, сейчас Report-Only), Twig auto-escaping, `symfony/html-sanitizer` для контента |
| CSRF-токен утечёт через `referer` или копию HTML | Низкая | Средний | Double-submit + same-origin subscriber + ротация токена при логине |
| 401-redirect-loop при истечении сессии | Средняя | Низкий | Fetch-wrapper отлавливает 401, делает `window.location = '/admin/login'`, не зацикливается |
| Vue bundle разрастается → медленный TTFB админки | Средняя | Низкий | Lazy routes + code-splitting + monitoring `npm run build` size в CI |
| Дублирование валидации (PHP + TS) | Высокая | Низкий | Серверная валидация — source of truth (DTO + Validator). На клиенте — только UX-подсказки, не блокирующие |
| Несовместимость нового Vue/Vite mаjor с TS | Средняя | Низкий | Lock-version в `package.json`, обновление отдельным PR с прогоном `vue-tsc --noEmit` |
| Sessions в файлах не масштабируются на N узлов | Средняя при росте | Высокий при росте | Перевод sessions в Redis (целевое); отдельный ADR при необходимости |

## Последствия

### Положительные

- Чёткая граница: SSR — только `/admin/login`, `/admin/logout`, shell-route. Всё остальное — Vue + JSON.
- API под `/admin/api/*` остаётся пригодным для будущих интеграций (мобильное приложение редактора, sync с CRM).
- Безопасность не деградирует: воспроизводим текущую модель CSRF + same-origin + cookie-session.
- Инкрементальная миграция: можно поэкранно переносить серверные admin-views в Vue.

### Отрицательные

- Нужны TypeScript-разработчики (или PHP-разработчики, готовые писать TS).
- Vue SPA bundle нужно поддерживать (build, lock, обновления).
- Тестирование требует комбинации PHPUnit functional (для shell + API) + JS unit/component тестов (Vitest — целевое).

## Что нужно сделать

1. Привести `templates/admin/dashboard.html.twig` к минимальному shell-виду (только `<div id="admin-app">` + meta-токены + Vite tags).
2. Реализовать в `assets/admin/main.ts`:
   - vue-router с `createWebHistory('/admin')`,
   - Pinia store,
   - fetch-wrapper с CSRF + 401-handler,
   - тип-генерация для DTO из бэкенд-DTO (целевое: через JSON Schema или вручную).
3. Перенести существующие admin CRUD-страницы (Page list, PageBlock editor, Settings, Redirects) в Vue.
4. Добавить Vitest + component-тесты (целевое).
5. Документировать паттерн в `12-admin-area.md` и `22-frontend-assets.md` (в этом же эпике).

## Когда пересмотреть

- Если возникает требование к мобильному админ-клиенту с офлайн-режимом → возможно понадобится JWT.
- Если admin-сессии нужно горизонтально масштабировать → отдельный ADR о переезде sessions в Redis (этот ADR-0012 не пересматривается).
- Если Vue 3 будет в EOL и нужно мигрировать на Vue 4 / другой фреймворк → пересмотреть выбор JS-стека (но не паттерн SSR-shell + SPA-island).
- Если SaaS-режим: понадобится мульти-тенант auth и публичный API → пересмотреть паттерн целиком.

## Связанные документы

- [12-admin-area.md](../12-admin-area.md)
- [22-frontend-assets.md](../22-frontend-assets.md)
- [20-security-and-access-control.md](../20-security-and-access-control.md)
- [ADR-0006](0006-separate-front-admin-api-dev-areas.md) (зоны Front/Admin/API/Dev)
- [ADR-0007](0007-redis-cache-and-messenger.md) (sessions/cache/messenger)
