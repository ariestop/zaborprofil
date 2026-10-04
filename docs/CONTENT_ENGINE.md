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

`content` и `settings` хранятся как нативный тип `JSON` в MySQL 8.4. MySQL нормализует JSON-объекты (порядок ключей объекта не сохраняется), порядок элементов массивов сохраняется. Тестовое окружение также
использует MySQL из Docker Compose; SQLite для тестов запрещён.

### PageRevision

`PageRevision` — immutable snapshot страницы на момент публикации или rollback-checkpoint.

Snapshot хранит:

- базовые поля страницы: `title`, `h1`, `slug`, `path`, `type`, `template`;
- SEO snapshot;
- blocks snapshot;
- settings snapshot;
- автора, дату, комментарий и summary изменения.

Публичный resolver сначала пытается отдать `publishedRevision` из `PagePublication`.
Если revision существует, рендер строится целиком из snapshot (поля страницы и
блоки) без смешивания с текущим draft/live состоянием билдера.

Если у старой страницы еще нет publication state, используется fallback на
опубликованную `Page`-сущность (legacy rollout без остановки сайта).

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

Поля шаблона:

- `kind` — `page` (шаблон целой страницы, выбирается при создании страницы) или `section` (набор блоков, который вставляется в конец существующей страницы);
- `blocksSchema` — список блоков в формате Page Builder (`type`, `name`, `position`, `content`, `settings`, `isEnabled`) и необязательным текстом-подсказкой `hint`: что именно нужно заполнить в блоке;
- `defaultSeo` — необязательные значения `metaTitle`, `metaDescription`, `ogTitle`, `ogDescription`, `ogType`, которые применяются к новой странице.

Системные шаблоны (`is_system = 1`) создаются миграциями: «Главная страница»,
«Страница услуги», «Страница материала», «Портфолио», «Контакты», «Прайс»,
«Текстовая страница», «SEO-посадочная», а также тематические «Забор из профнастила»,
«Забор-жалюзи», «Ворота и калитки». Блоки шаблонов используют структурированные
типы (`hero.classic`, `rich-text`, `features`, `price-table`, `faq`, `cta`,
`contact-form` и др.) с текстами-заготовками. Блоки проверяются теми же правилами,
что и сохранение в Page Builder (`PageTemplateBlocks`), поэтому невалидный
шаблон нельзя ни сохранить, ни применить. Новые системные шаблоны добавляются
только миграциями (воспроизводимость dev-БД), а не вручную на стенде.

Пользовательские шаблоны (`custom_<ulid>`) создаются из админки: «Сохранить страницу
как шаблон» и «Сохранить как шаблон секции» в редакторе блоков. Удалить (деактивировать)
можно только пользовательский шаблон. Управление шаблонами требует права
`pages.manage_templates`: без него в редакторе страницы скрыты кнопки «Сохранить страницу
как шаблон», «Сохранить как шаблон секции», «Вставить секцию из шаблона» и удаление
пользовательских шаблонов (сервер дополнительно проверяет право на каждый запрос).

### Создание страницы из шаблона

`POST /admin/api/content/pages` принимает необязательное поле `starterTemplate`
(код шаблона `page`). Страница и блоки шаблона сохраняются одной операцией:
если шаблон не найден или его блоки невалидны, страница не создаётся.

### Дублирование страницы

`POST /admin/api/content/pages/{id}/duplicate` (право `pages.create`) создаёт
черновик-копию: блоки (с новыми id) и SEO (`metaTitle`, `metaDescription`, OG,
JSON-LD) копируются, `canonicalUrl` не копируется (иначе копия указывала бы на оригинал).
Адрес и slug генерируются заново: `/path-copy/`, `/path-copy-2/`, … Можно передать
свои `title`, `slug`, `path`. Если `path` занят, API возвращает `422`.

### Массовые действия над страницами

`POST /admin/api/content/pages/bulk` с телом `{ "ids": [...], "action": "status", "status": "review" }`
или `{ "ids": [...], "action": "indexable", "indexable": false }` (до 100 страниц).
Каждая страница обрабатывается независимо, в ответе — результат по каждой:
`{ "results": [{ "id", "ok", "error" }], "succeeded", "failed" }`. Права проверяются
по целевому статусу так же, как для `PATCH .../status`, для индексации нужно право `seo.edit`.
Публикация и планирование через массовые действия недоступны: перед публикацией
нужна проверка SEO по каждой странице. Смена индексации у опубликованной страницы
сразу обновляет и опубликованную ревизию, поэтому `noindex` действует на сайте без
повторной публикации.

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

## Structured Visual CMS Builder

В админке используется **Structured Visual CMS Builder** (без GrapesJS):

- страница состоит из управляемого списка блоков;
- каждый блок хранится как JSON (`content` + `settings`) и валидируется на backend;
- свободный HTML-конструктор и custom JS-блоки на первом этапе запрещены;
- rich text допускается только через безопасный pipeline (TipTap + backend sanitize).

Единый контракт блока:

```json
{
  "id": "uuid",
  "type": "hero.classic",
  "enabled": true,
  "position": 10,
  "content": {},
  "settings": {},
  "metadata": {
    "createdAt": "2026-05-09T00:00:00+00:00",
    "updatedAt": "2026-05-09T00:00:00+00:00"
  }
}
```

### Единый реестр типов блоков и legacy-типы

Backend (`BlockType`) поддерживает два семейства типов:

- **structured** — актуальные типы визуального builder (`hero.classic`, `rich-text`, `features`, `cta`, ...);
- **legacy** — типы первых версий content API (`hero`, `text`, `text_image`, `feature_grid`, `cta_form`, ...),
  которые уже сохранены в БД и рендерятся Twig-шаблонами.

Источник правды для обоих семейств — файл `config/content/block-types.json`
(`structured` — список типов, `legacy` — карта «legacy-тип → канонический structured-тип или `null`»).
Его читает backend (`BlockTypeCatalog`) и сверяет с `BlockType` unit-тест, а frontend админки держит
синхронную копию в `admin/modules/page-builder/types.ts` (`STRUCTURED_BLOCK_TYPES`, `LEGACY_BLOCK_TYPES`,
`LEGACY_BLOCK_TYPE_ALIASES`) и сверяет её со вторым файлом тестом vitest. Добавление нового типа без обновления
JSON, enum и frontend-реестра приводит к падению тестов.

В builder legacy-блоки открываются и сохраняются без изменения типа и данных: они не показываются в каталоге
добавления блоков, редактируются через JSON-панель, а их схемы пропускают любые поля. Канонический аналог
виден в `GET /admin/api/content/block-schemas` (поле `canonicalType`). Автоматической конвертации данных legacy → structured
нет: формы `content` у типов различаются, поэтому миграция данных не выполнялась.

### Контракт `content` и `settings`

В API-ответах (`/blocks`, `/pages/{id}`, `/builder`, `blocksSnapshot` ревизий) `content` и `settings` всегда JSON-объекты.
Пустой PHP-массив `[]` сериализуется как `{}` (`JsonObject::from()` в DTO). Ранее такие блоки приходили с `"settings": []`,
и builder не мог их сохранить. Старые данные в БД (`[]` в JSON-колонках) не требуют миграции: нормализация выполняется при
сериализации. На входе `settings`/`content` принимают `{}`, `[]` и `null` (как пустой объект), непустой список даёт `422`.
Frontend дополнительно приводит ответы API к объектам (`blockSerialization.ts`) и при сохранении отправляет блоки
в порядке builder с последовательными `position`.

## Admin API

API защищен admin firewall и используется React + TypeScript админкой.

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

### Builder API (основной контракт для визуального редактора)

- `GET /admin/api/content/pages/{id}/builder`
- `PUT /admin/api/content/pages/{id}/builder`
- `POST /admin/api/content/pages/{id}/builder/preview`
- `POST /admin/api/content/pages/{id}/builder/publish`

`PUT /builder` принимает массив блоков structured-контракта и синхронизирует
состав/порядок/включенность блоков страницы атомарно.

Ответы `GET`/`PUT /builder` содержат `version` — хэш содержимого блоков. `PUT /builder` принимает необязательное
поле `baseVersion`: при расхождении с текущей версией возвращается `409` с `code: "EDIT_CONFLICT"` и актуальными
`version`/`updatedAt` (подробности и мягкая блокировка `POST|DELETE /{id}/edit-lock` — в
[admin/page-editor](admin/page-editor.md)).

Для block type `text` и `text_image` в админке предусмотрен визуальный режим редактирования на базе Vue TipTap.
Он работает поверх тех же полей `content/settings` и сохраняет HTML в `content.text` (JSON) без изменения API-контракта.
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
- TTL по умолчанию — 300 секунд;
- изменения страниц, блоков и SEO metadata сбрасывают path-specific cache;
- изменения settings, robots.txt и redirects сбрасывают глобальный public page cache tag.

Preview-ссылки создаются через `GET /admin/api/content/pages/{id}/preview-link` и подписываются HMAC-токеном. Preview доступен для draft/published/archived страниц по `/_preview/content/pages/{id}/{token}` и всегда отдаёт `X-Robots-Tag: noindex,nofollow` + meta robots `noindex, nofollow`.

Текущие partials включают legacy и structured-варианты, в том числе:

- `templates/public/blocks/hero.html.twig`
- `templates/public/blocks/hero.classic.html.twig`
- `templates/public/blocks/features.html.twig`
- `templates/public/blocks/rich-text.html.twig`
- `templates/public/blocks/contact-form.html.twig`
- `templates/public/blocks/cta.html.twig`
- `templates/public/blocks/default.html.twig`

Часть structured-типов дополнительно маппится на legacy partial через алиасы в
`TwigBlockRenderer` (например, `features -> feature_grid`, `cta -> cta_form`).

## Тесты

Покрытие:

- `tests/Unit/Content/PageTest.php`
- `tests/Integration/Content/DoctrineContentRepositoryTest.php`
- `tests/Functional/Content/AdminContentApiTest.php`
- `tests/Unit/Content/BlockTypeCatalogTest.php` (реестр типов совпадает с `BlockType`)
- `admin/modules/page-builder/registry/blockRegistry.spec.ts`, `admin/modules/page-builder/utils/blockSerialization.spec.ts` (vitest: реестр frontend и нормализация ответов API)
- `tests/e2e/admin-smoke.spec.ts` (DnD + сохранение порядка блоков, созданных через content API)

Запуск:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```
