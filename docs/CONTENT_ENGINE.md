# Content Engine

`Content` — базовое контентное ядро CMS для ручного переноса страниц со старого WordPress-сайта.

Автоматический импорт WordPress, shortcode parser и перенос структуры WordPress 1-в-1 не реализуются.

## Модель

### Page

`Page` описывает страницу сайта.

Ключевые поля:

- `id`
- `parent`
- `type`
- `title`
- `slug`
- `path`
- `h1`
- `status`
- `template`
- `sortOrder`
- `isIndexable`
- `publishedAt`
- `createdAt`
- `updatedAt`
- `deletedAt`

`path` уникален и задается вручную. Это нужно для сохранения SEO-совместимых URL старого сайта.

### PageBlock

`PageBlock` описывает блок страницы.

Ключевые поля:

- `id`
- `page`
- `type`
- `name`
- `position`
- `isEnabled`
- `content`
- `settings`
- `createdAt`
- `updatedAt`

`content` и `settings` хранятся как JSONB в PostgreSQL. Тестовое окружение также
использует PostgreSQL из Docker Compose; SQLite для тестов запрещён.

### PageRevision

`PageRevision` — immutable snapshot страницы на момент публикации или rollback-checkpoint.

Snapshot хранит:

- базовые поля страницы: `title`, `h1`, `slug`, `path`, `type`, `template`;
- SEO snapshot;
- blocks snapshot;
- settings snapshot;
- автора, дату, комментарий и summary изменения.

Публичный resolver сначала пытается отдать `publishedRevision` из `PagePublication`.
Если у старой страницы еще нет publication state, используется прежний fallback на
текущие `Page` + `PageBlock`. Это нужно для staged rollout без остановки сайта.

### PagePublication

`PagePublication` хранит указатели на:

- current revision;
- draft revision;
- published revision;
- scheduled revision;
- last published/unpublished timestamps.

Публикация создает новую revision и переключает `publishedRevision`.

### PageTemplate

`PageTemplate` описывает системные и пользовательские шаблоны страниц. Системные
шаблоны создаются миграцией и не должны удаляться редакторами. При создании
страницы из шаблона блоки копируются в editable draft state.

## Статусы

- `draft` — черновик, публично не показывается.
- `review` — ожидает проверки, публично не показывается.
- `approved` — одобрено к публикации, но ещё не опубликовано.
- `published` — опубликованная страница, доступна по `path`.
- `scheduled` — запланировано к публикации.
- `unpublished` — снято с публикации.
- `archived` — архив, публично не показывается.
- `deleted` — soft deleted.

Переходы статусов проверяются `PageStatusTransitionPolicy`. Редактор может
создавать и отправлять страницы на review; SEO может approve; admin/super admin
управляют публикацией, снятием, архивом, restore и rollback.

## Admin API

API защищен admin firewall и предназначен для будущей Vue-админки.

### Создать Страницу

`POST /admin/api/content/pages`

```json
{
  "type": "landing",
  "title": "Забор жалюзи",
  "slug": "zabor-jaluzi",
  "path": "/zabor-jaluzi/",
  "h1": "Забор жалюзи",
  "template": "landing",
  "sortOrder": 0,
  "isIndexable": true
}
```

### Обновить Страницу

`PUT /admin/api/content/pages/{id}`

Тело запроса совпадает с созданием страницы.

### Опубликовать Или Архивировать

- `POST /admin/api/content/pages/{id}/publish`
- `POST /admin/api/content/pages/{id}/archive`

### Добавить Блок

`POST /admin/api/content/pages/{pageId}/blocks`

```json
{
  "type": "hero",
  "name": "Главный экран",
  "position": 0,
  "content": {
    "title": "Забор жалюзи под ключ",
    "text": "Производство и монтаж."
  },
  "settings": {},
  "isEnabled": true
}
```

### Управление Блоками

- `PUT /admin/api/content/blocks/{id}`
- `POST /admin/api/content/pages/{pageId}/blocks/reorder`
- `DELETE /admin/api/content/blocks/{id}`

Для block type `text` и `text_image` в админке предусмотрен визуальный режим редактирования на базе Vue TipTap.
Он работает поверх тех же полей `content/settings` и сохраняет HTML в `content.text` (JSONB) без изменения API-контракта.
Для сложных кейсов доступен fallback-режим ручного JSON.

Для `text_image` выбор изображения выполняется через существующий Media API:

- `GET /admin/api/media/assets` — список файлов;
- `POST /admin/api/media/assets` — загрузка новых файлов.

Для сортировки используется список `blockIds` в нужном порядке:

```json
{
  "blockIds": [
    "01HY...",
    "01HZ..."
  ]
}
```

## Публичный Рендер

Публичный route ищет страницу по `Page.path`.

Правила:

- открывается только `published`;
- `draft` и `archived` возвращают `404`;
- блоки выводятся по `position`;
- блоки с `isEnabled=false` не выводятся;
- Twig partial выбирается по `BlockType`.

Публичный рендер кэшируется через `cache.public_page`:

- cache key строится от нормализованного `Page.path`;
- cache item получает глобальный tag и path-specific tag;
- изменения страниц, блоков и SEO metadata сбрасывают path-specific cache;
- изменения settings, robots.txt и redirects сбрасывают глобальный public page cache tag.

Preview-ссылки создаются через `GET /admin/api/content/pages/{id}/preview-link` и подписываются HMAC-токеном. Preview доступен для draft/published/archived страниц по `/_preview/content/pages/{id}/{token}` и всегда отдаёт `X-Robots-Tag: noindex,nofollow` + meta robots `noindex, nofollow`.

Текущие partials:

- `templates/public/blocks/hero.html.twig`
- `templates/public/blocks/text.html.twig`
- `templates/public/blocks/seo_text.html.twig`
- `templates/public/blocks/default.html.twig`

## Тесты

Покрытие:

- `tests/Unit/Content/PageTest.php`
- `tests/Integration/Content/DoctrineContentRepositoryTest.php`
- `tests/Functional/Content/AdminContentApiTest.php`

Запуск:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
