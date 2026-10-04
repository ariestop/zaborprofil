# 22. Frontend assets

## Стек

- **Vite 7** — bundler.
- **React 19 + TypeScript** — admin SPA.
- **Tailwind CSS 4** + `@tailwindcss/typography` (конфигурация в CSS, без `tailwind.config.ts`).
- **TypeScript 5.8**, `tsc --noEmit` для типов.
- **PostCSS** с плагином `@tailwindcss/postcss` (префиксы добавляет сам Tailwind 4).
- **Node.js >= 25.9.0**, npm >= 11.12.1.

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md) для деталей admin shell.

## Где живёт

```text
assets/
├── admin/         # React admin SPA entry: app.ts, components/, views/, ...
└── site/          # Public site: main.ts/css, partial JS для публичного сайта
public_html/
└── build/         # Vite output (gitignored)
```

`vite.config.ts` использует два entry: `assets/site/app.ts` и `assets/admin/app.ts` (один `manifest.json`).

## Build

```bash
npm install
npm run build       # tsc --noEmit && vite build → public_html/build/
```

В Docker:

```bash
make npm-install
make npm-build
```

## Dev server (только локально)

```bash
# 1. В .env.local: VITE_DEV_SERVER_URL=http://localhost:5173
# 2. В отдельном терминале:
make npm-dev        # vite dev на :5173, HMR
```

Режим включается **только** переменной `VITE_DEV_SERVER_URL` (параметр `app.vite.dev_server_url`).
Пока она не задана, страницы берут файлы из `manifest.json`, и изменения видны только после
`make npm-build`. В `prod` и `test` параметр принудительно пустой.

Пока переменная задана, а `make npm-dev` не запущен, страницы остаются без стилей и JS.
Для проверки production-сборки закомментируйте переменную и выполните `make npm-build`.
Если проект лежит на файловой системе без inotify (например, `C:\` под Docker Desktop),
запускайте `VITE_USE_POLLING=1 make npm-dev`.

## Подключение в Twig

```twig
{# base.html.twig (публичный сайт): CSS входа #}
{{ vite_entry_link_tags('assets/site/app.ts') }}

{# admin/dashboard.html.twig #}
{{ vite_entry_link_tags('assets/admin/app.ts') }}
{{ vite_entry_script_tags('assets/admin/app.ts') }}
```

`ViteAssetExtension`:

- в production читает `public_html/build/.vite/manifest.json` и возвращает хешированные пути;
- в dev-режиме подключает с `VITE_DEV_SERVER_URL` клиент HMR, React Fast Refresh и сам вход;
  CSS приходит через модуль входа, поэтому каждый вход выводится на странице один раз
  (вторая функция для того же входа возвращает пустую строку).

## Cache busting

- Все ассеты в production имеют hash в имени файла (Vite default).
- Браузеры кешируют по hash; новый build = новые URL.
- Nginx ставит `Cache-Control: public, max-age=31536000, immutable` для `/build/*`.

## Tailwind

Вся конфигурация живёт в `assets/shared/styles/app.css`:

- `@import 'tailwindcss' source(none)` и `@source` для `templates/` и `assets/`: Tailwind 4 по умолчанию сканирует весь проект, поэтому источники ограничены явно.
- `@plugin '@tailwindcss/typography'`.
- `@theme`: кастомные цвета (`brand-*`) и шрифт `--font-sans` (оставлен стек из Tailwind 3). Кастомные значения добавляются сюда, не inline.
- `@layer base`: совместимость с v3 (цвет границы по умолчанию `gray-200`, курсор `pointer` у кнопок).

Файла `tailwind.config.ts` больше нет. Поддерживаемые браузеры Tailwind 4: Safari 16.4+, Chrome 111+, Firefox 128+.

Запрещено: hand-rolled CSS, конфликтующий с Tailwind классами без причины.

## Public site assets

Публичный сайт — преимущественно SSR. JS на публичных страницах — минимальный (формы, lightbox, аналитика).

## Admin SPA

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md). Единый entry `assets/admin/app.ts`
грузится в `templates/admin/dashboard.html.twig` и стартует React-приложение в
`<div id="admin-app">`. CSRF token читается из `<meta name="admin-csrf-token">`.

## Image optimization (целевое)

- `vite-imagetools` или отдельный pipeline для генерации `srcset`.
- WebP/AVIF варианты.
- `Cache-Control: immutable` для производных.

## Production build на VPS

```bash
npm ci
npm run build
```

Запускается внутри `tools/deploy/deploy-*.sh`. Готовый `public_html/build/` остаётся в release-папке. Никаких dev-зависимостей в runtime — Node на VPS не запускается, только используется для build.

## Что НЕЛЬЗЯ

- Импортировать Vue в публичных Twig-страницах ради «чуть-чуть интерактивности» — это разрушает план SSR.
- Использовать CDN-ссылки вместо локального build (нарушает оффлайн staging, security headers, CSP).
- Хардкодить пути к ассетам в Twig — только через `vite_asset()`.
- Хранить ассеты в `public_html/build/` руками — это вывод Vite, не источник.

## Чек-лист добавления frontend-фичи

- [ ] Файл в `assets/site/...` или `assets/admin/...`.
- [ ] Используется TypeScript, типы прописаны.
- [ ] Tailwind классы (а не custom CSS) для оформления.
- [ ] `npm run build` проходит без warnings.
- [ ] Twig подключает через `vite_asset(...)`.
- [ ] CSP проверен, если добавляются inline-handlers.
- [ ] `tsc --noEmit` не ругается.

## Связанные документы

- [21-templates-and-twig](21-templates-and-twig.md)
- [12-admin-area](12-admin-area.md)
- [33-local-development](33-local-development.md)
- [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md)
