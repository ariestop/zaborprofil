# 22. Frontend assets

## Стек

- **Vite 7** — bundler.
- **React 19 + TypeScript** — admin SPA.
- **Tailwind CSS 3** + `@tailwindcss/typography`.
- **TypeScript 5.8**, `tsc --noEmit` для типов.
- **PostCSS**, `autoprefixer`.
- **Node.js >= 25.9.0**, npm >= 11.12.1.

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md) для деталей admin shell.

## Где живёт

```text
admin/             # React admin SPA: app.ts, components/, pages/, modules/, ...
assets/
└── site/          # Публичный сайт: app.ts и прочие entry для SSR-страниц
public_html/
└── build/         # Вывод Vite (обычно gitignored)
```

`vite.config.ts` использует два entry: `assets/site/app.ts` и `admin/app.ts` (один `manifest.json`).

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
make npm-dev        # vite dev на :5173, HMR
```

`ViteAssetExtension` определяет, использовать manifest или dev server (по наличию `manifest.json`).

## Подключение в Twig

```twig
{# В base.html.twig #}
<link rel="stylesheet" href="{{ vite_asset('assets/site/main.ts') }}">
{{ vite_styles() }}
<script type="module" src="{{ vite_asset('admin/app.ts') }}"></script>
```

`ViteAssetExtension`:

- читает `public_html/build/.vite/manifest.json`;
- возвращает финальные хешированные пути;
- в dev — отдаёт `http://localhost:5173/...`.

## Cache busting

- Все ассеты в production имеют hash в имени файла (Vite default).
- Браузеры кешируют по hash; новый build = новые URL.
- Nginx ставит `Cache-Control: public, max-age=31536000, immutable` для `/build/*`.

## Tailwind

`tailwind.config.ts`:

- `content`: `templates/**/*.html.twig`, `assets/**/*.{ts,tsx}`, `admin/**/*.{ts,tsx}`.
- `plugins`: `@tailwindcss/typography`.
- Кастомные цвета/шрифты — в config, не inline.

Запрещено: hand-rolled CSS, конфликтующий с Tailwind классами без причины.

## Public site assets

Публичный сайт — преимущественно SSR. JS на публичных страницах — минимальный (формы, lightbox, аналитика).

Для блока `slider` используется локально установленный `swiper`:

- JS и CSS импортируются в `assets/site/app.ts`;
- инициализация выполняется только для элементов `.js-site-slider`;
- настройки (`autoplay`, `loop`, `pagination`, `navigation`, `delayMs`) передаются из SSR через `data-slider-settings`.

## Admin SPA

См. [ADMIN_FRONTEND.md](ADMIN_FRONTEND.md). Единый entry `admin/app.ts`
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

- [ ] Файл в `assets/site/...` или `admin/...`.
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
