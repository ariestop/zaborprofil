# SEO

SEO является критичной частью проекта.

## Обязательные правила

- URL страниц задаются вручную и могут повторять старые URL WordPress-сайта.
- `title`, `description`, `h1`, `canonical`, `robots` управляются из админки.
- Canonical URL должен принадлежать домену из `SITE_URL`; внешний canonical отклоняется guard-ом.
- Черновики не попадают в sitemap.
- Preview-ссылки всегда получают `noindex,nofollow`.
- Служебные страницы должны получать `noindex`.
- Изменение URL должно сопровождаться 301 redirect.

## Текущая SEO-база

- `Page.indexable` управляет `<meta name="robots">`.
- `Page.metaDescription`, `canonicalUrl`, `ogTitle`, `ogDescription`, `ogImage`, `ogType`, `jsonLd` выводятся в Twig `<head>`.
- `SchemaOrgBuilder` автоматически добавляет базовый `WebPage` JSON-LD для публичной страницы; редакторские JSON-LD блоки добавляются следом.
- `/sitemap.xml` автоматически становится sitemap index, когда опубликованных индексируемых страниц больше `app.sitemap_chunk_size`; чанки доступны как `/sitemap-pages-N.xml`.
- `/robots.txt` в production управляется через `PUT /admin/api/seo/robots`; вне production всегда отдается `Disallow: /`.
- Публичные страницы кэшируются через `cache.public_page`; изменения страниц, блоков и SEO сбрасывают соответствующий cache tag.
- Управляемые меню доступны через `menu_items(position)` и могут использоваться для breadcrumbs/навигации.

## Будущий SEO-аудит

Команда `app:seo:audit` должна проверять:

- пустые title, description и h1;
- дубли URL;
- страницы без canonical;
- опубликованные страницы вне sitemap;
- изображения без alt;
- пустые блоки;
- битые внутренние ссылки;
- валидность schema.org.
