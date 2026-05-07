# 21. Templates и Twig

## Структура

```text
templates/
├── base.html.twig                 # общий layout: <head>, header, footer
├── admin/
│   ├── dashboard.html.twig        # admin shell с CSRF meta
│   └── security/login.html.twig
└── public/
    ├── home.html.twig
    ├── page/show.html.twig        # шаблон публичной страницы
    └── blocks/                    # partial’ы блоков по BlockType
        ├── default.html.twig
        ├── hero.html.twig
        ├── seo_text.html.twig
        └── text.html.twig
```

## Layout

`base.html.twig`:

- `<head>`: title, meta description, canonical, robots, OpenGraph, Twitter card image и JSON-LD blocks; SEO-переменные передаются из публичных контроллеров (см. [26-seo-architecture](26-seo-architecture.md)).
- `<header>`, `<footer>` и service navigation рендерят управляемые `menu_items(position)`.
- Tailwind classes — Atomic подход.

## Page render

`public/page/show.html.twig`:

- Получает `page` (Domain entity) и `blocks` (массив отрендеренных partial’ов через `TwigBlockRenderer`).
- Не вызывает Doctrine.
- Не делает бизнес-вычислений.
- Только выводит готовое.

## Partial’ы блоков

- Один partial = один `BlockType`.
- Если тип неизвестен — fallback `default.html.twig`.
- `TwigBlockRenderer` (UI/Web) — application-level helper, выбирает правильный partial и передаёт ему уже подготовленные данные.

## Twig extensions

| Extension | Что делает |
|---|---|
| `App\Shared\UI\Twig\ViteAssetExtension` | `vite_asset(...)`, `vite_styles()` — читает manifest, отдаёт правильные пути |
| `App\Module\Settings\UI\Twig\SettingsTwigExtension` | `setting('key', default)` — читает Setting через `SettingsService` |

Добавление нового extension:

- Класс наследует `Twig\Extension\AbstractExtension`.
- Методы — `TwigFunction` / `TwigFilter`.
- Логика — тонкая, делегирует в Application/Service.
- Юнит-тест с моками.

## View models

Если нужно много данных в шаблон — собирать **view model** в Application/Controller:

```php
final class PublicPageView
{
    public function __construct(
        public readonly string $title,
        public readonly string $h1,
        public readonly string $path,
        public readonly array $blocks,
        public readonly array $seo,
    ) {
    }
}
```

В Twig доступны только `view.title`, `view.h1`, и т.д. Никаких `view.repository.findAll(...)`.

## Что нельзя в Twig

- Doctrine query, Repository вызовы.
- `app.session` для бизнес-разветвлений (только для CSRF).
- `app.user` для сложного RBAC — использовать `is_granted('PERMISSION')`.
- Сложные condition trees / расчёты — выносить в Twig extension с явным контрактом.
- Тяжёлые `for` циклы с N+1 Doctrine.
- HTML/JS, генерируемый из user input без escaping (`|raw` запрещён без явного безопасного источника).

## Escaping

- По умолчанию — `html`.
- Для `<script>` блоков — `|json_encode|raw` через trusted helper.
- Для URL — `|escape('url')` или `|url_encode`.
- Никаких `{{ user_html|raw }}` без HTML sanitizer.

## i18n / переводы

- Translations подключены (`config/packages/translation.yaml`).
- На текущем этапе сайт на русском, без перевода интерфейса.
- Целевое: ICU-format, `translations/messages.ru.yaml`, `messages.en.yaml`.

## Cache busting

Vite manifest содержит хеши. `ViteAssetExtension` отдаёт `<link rel="stylesheet" href="/build/site-Bxa12.css">`. Браузеры кешируют ассет по hash; новый билд — новый hash.

## Linting

- `php bin/console lint:twig templates --no-interaction` — в CI.
- В IDE — Twig support через PhpStorm или Cursor extension.

## Anti-patterns

- Сложные `if`/`for` в Twig вместо подготовленного view model.
- Доступ к `entity.repository` через сервис (через global twig variable) — обход Application.
- Раскопка структуры Doctrine collection в Twig для обхода lazy load.
- Inlining `<script>` с user-data без `|json_encode`.
- Mass-копирование partial’ов вместо `include`/`embed`.

## Чек-лист добавления шаблона

- [ ] Лежит в `templates/<area>/<resource>/...`.
- [ ] Расширяет `base.html.twig` (или admin layout).
- [ ] Получает только примитивы и view model.
- [ ] Никаких Doctrine вызовов.
- [ ] Все user-input выводы — escaped.
- [ ] `lint:twig` без ошибок.

## Связанные документы

- [13-front-area](13-front-area.md)
- [22-frontend-assets](22-frontend-assets.md)
- [26-seo-architecture](26-seo-architecture.md)
- [27-config-and-env](27-config-and-env.md)
