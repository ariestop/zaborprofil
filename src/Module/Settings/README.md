# Settings

Глобальные настройки сайта (feature flags, интеграции, режимы рендера).

## Используемые ключи

- `content.public_page_blocks_source`:
  - `snapshot` (по умолчанию) — публичный фронт рендерит блоки из `publishedRevision` snapshot;
  - `live` — публичный фронт рендерит актуальные live-блоки из builder для опубликованных страниц.
- `seo.title_template` — шаблон `<title>` для страниц без собственного SEO-title; подстановки `{title}`, `{h1}`, `{site_name}`; пусто = название страницы.
- `seo.site_name` — значение для `{site_name}` (по умолчанию «ЗаборПрофиль»).
