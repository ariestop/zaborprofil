# ADR-0010: SEO-first CMS architecture

## Статус

Accepted, 2026.

## Контекст

`zaborprofil.ru` — корпоративный сайт, у которого SEO-трафик является ключевым каналом получения клиентов. Любая регрессия — прямые финансовые потери.

WordPress-предшественник имеет накопленный SEO-вес, нестандартные URL, существующие позиции в выдаче. Миграция должна:

- сохранять старые URL без изменения;
- давать редактору контроль над title/description/canonical/robots/h1;
- иметь sitemap, robots, redirects;
- не давать черновикам и архивным страницам публично индексироваться;
- быть SSR (поисковые роботы не должны исполнять SPA для основного контента).

## Решение

CMS строится как **SEO-first**:

1. **URL-стратегия.** `Page.path` задаётся вручную; partial unique index на живых записях.
2. **SSR через Twig.** Никакой SPA для публичной зоны.
3. **Управляемые SEO-поля.** Title/description/h1/canonical/robots/indexable — в Domain (`Page`; выделение embedded `SeoMetadata` остаётся возможным future refactor).
4. **Redirects как first-class entity.** `Module\Seo\Domain\Entity\Redirect` + `RedirectKernelSubscriber` обрабатывают 301/302 до Router.
5. **Sitemap + robots.** Контроллеры в `Module\Seo\UI\Web`. На staging robots = `Disallow: /`.
6. **Защита черновиков.** `Draft`/`Archived` всегда возвращают 404 публично.
7. **Кеш.** `cache.public_page` ускоряет публичный SSR; инвалидация при publish/update/archive.
8. **PagePathChangeListener.** Доменное правило: смена `path` опубликованной страницы должна порождать `Redirect`.
9. **SEO checklist** — обязательная часть feature-development guide ([42-feature-development-guide](../42-feature-development-guide.md)).
10. **Целевой `app:seo:audit` console command** — проверяет дубли, отсутствие title/description, страницы без canonical и т.п.

## Причины

- **Бизнес-критичность SEO.** Регрессии равны потере клиентов.
- **Bewährter подход.** SSR + контроль URL — стандарт для CMS с SEO.
- **Простота для редакторов.** Поля title/description управляются в админке, без необходимости понимать код.

## Последствия

- Публичный сайт не может быть SPA. Любой push в эту сторону требует ADR на отмену этого.
- Любое изменение `Page.path` требует одновременного создания `Redirect` (это правило проверяется в листенере / handler / тесте).
- В sitemap.xml — только опубликованные индексируемые страницы.
- В тестах есть functional-тесты на 301 redirects, sitemap и robots.

## Альтернативы

- **SPA-only публичный сайт.** Хуже для SEO, требует SSR-prerender инфраструктуру (Nuxt/Next).
- **Хранить URL в виде только `slug` + parent id.** Усложняет совместимость со старыми WordPress URL.
- **Динамический `path` на основе slugs.** Те же проблемы.

## Когда пересмотреть

- Появится отдельный SSR-фреймворк (Inertia / Nuxt) с приемлемой производительностью и сложностью.
- Бизнес отказывается от SEO как ключевого канала (маловероятно).
- Появится требование multi-language с разной URL-структурой — пересмотреть в сторону `Translation`-подсистемы.

## Связанные документы

- [26-seo-architecture](../26-seo-architecture.md)
- [13-front-area](../13-front-area.md)
- [05-domain-model](../05-domain-model.md)
- [SEO_GUIDE.md](../SEO_GUIDE.md)
- [REDIRECTS.md](../REDIRECTS.md)
