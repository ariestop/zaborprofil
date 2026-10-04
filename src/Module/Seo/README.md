# Seo

SEO-инструменты сайта: редиректы, robots.txt, журнал 404, sitemap, schema.org и SEO-аудит страниц.

- `Application/Redirect` — валидация правил (`RedirectRuleValidator`), граф циклов и цепочек (`RedirectGraph`, `RedirectAnalyzer`), CRUD-сценарии (`RedirectManager`), CSV-импорт/экспорт.
- `Application/Robots` — проверка синтаксиса robots.txt.
- `Application/NotFound` — запись публичных 404 в агрегированный журнал.
- `UI/Admin` — тонкие контроллеры Admin API (`/admin/api/seo/...`), ошибки через `AdminApiErrorResponder`.

Описание контракта, API и правил: [docs/26-seo-architecture.md](../../../docs/26-seo-architecture.md), разделы 5 и 7.
