# Settings

Глобальные настройки сайта (feature flags, интеграции, режимы рендера).

## Используемые ключи

- `content.public_page_blocks_source`:
  - `snapshot` (по умолчанию) — публичный фронт рендерит блоки из `publishedRevision` snapshot;
  - `live` — публичный фронт рендерит актуальные live-блоки из builder для опубликованных страниц.
