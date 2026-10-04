# 23. Cache

## Стек

- Symfony Cache с адаптером `cache.adapter.filesystem` (PSR-6/PSR-16, `symfony/cache`).
- Каталог по умолчанию — `%kernel.cache_dir%/pools/app`, то есть `var/cache/<env>/pools/app`.
- Внешних сервисов (Redis, Memcached) нет: кэш работает на хостинге без дополнительных демонов, в том числе на staging на Beget.
- Решение зафиксировано в [ADR-0007](adr/0007-filesystem-cache-and-doctrine-messenger.md).

## Конфигурация

`config/packages/cache.yaml`:

```yaml
framework:
    cache:
        app: cache.adapter.filesystem
        system: cache.adapter.system
        pools:
            cache.public_page:
                adapter: cache.app
                default_lifetime: 3600
                tags: true
            cache.settings:
                adapter: cache.app
                default_lifetime: 86400
            cache.menu:
                adapter: cache.app
                default_lifetime: 86400
            cache.seo:
                adapter: cache.app
                default_lifetime: 86400

when@test:
    framework:
        cache:
            app: cache.adapter.array
            pools:
                cache.public_page:
                    adapter: cache.adapter.array
                    tags: true
                # cache.settings, cache.menu, cache.seo — тоже cache.adapter.array
```

В `test` все пулы — `cache.adapter.array` (быстрее, изолирует тесты, ничего не пишет на диск).

Пулы строятся поверх `cache.app` (filesystem). Пул `cache.public_page` объявлен с `tags: true`, поэтому Symfony оборачивает его в tag-aware адаптер и регистрирует как `TagAwareCacheInterface`; теги хранятся в тех же файловых пулах.

## Файловый кэш: свойства и ограничения

| Свойство | Что это значит на практике |
|---|---|
| Локальность | Кэш лежит в `var/cache/<env>/pools/app` конкретного релиза и **не разделяется** между серверами |
| Release-based деплой | У каждого релиза свой `var/cache`, поэтому после переключения `current` кэш холодный: нужен прогрев (`cache:warmup`, запрос основных страниц) |
| Права на запись | Пользователь PHP-FPM и CLI (messenger worker, deploy) должен писать в `var/cache`; иначе кэш молча деградирует до промахов и растёт число `cache.error` |
| TTL | Соблюдается: протухшие записи удаляются при чтении и при `cache:pool:prune` |
| Теги | Инвалидация по тегам работает через `TagAwareAdapter` над файловым пулом; теги хранятся отдельными файлами |
| Pub/sub, атомарные счётчики | Отсутствуют; для этого файловый кэш не предназначен |
| Idempotency-key, антиспам лидов | Используют `cache.app`; TTL соблюдается, но счётчики не атомарны между параллельными процессами |
| Размер | Растёт без ограничений до выполнения `cache:pool:prune` или `cache:clear`; следить за `du -sh var/cache/prod/pools` |
| Горизонтальное масштабирование | Потребует общей файловой системы либо возврата к сетевому кэшу (Redis) — см. «Когда пересмотреть» в ADR-0007 |

Файловый кэш **не** является источником истины: любая запись должна безопасно пересоздаваться из БД.

## Пулы

| Пул | TTL | Что хранится |
|---|---|---|
| `cache.app` | n/a (общий) | Общий filesystem-пул: Doctrine result cache (в `prod`), антиспам лидов, Idempotency-key, generic prewarm |
| `cache.system` | n/a | Symfony system cache (PHP arrays, `cache.adapter.system`) |
| `cache.public_page` | 3600 (по умолчанию пула) | HTML/view-model публичных страниц по `Page.path`, с тегами |
| `cache.settings` | 86400 | Settings registry (читается часто) |
| `cache.menu` | 86400 | Меню для публичной зоны |
| `cache.seo` | 86400 | SEO-метаданные / sitemap chunks |

## Использование

**Фактическое состояние** ([`SettingsService`](../src/Module/Settings/Application/Service/SettingsService.php)) — использует default `cache.app` через простой `CacheInterface` без `#[Target]`:

```php
final readonly class SettingsService
{
    private const string CACHE_PREFIX = 'settings.';

    public function __construct(
        private SettingRepositoryInterface $settings,
        private CacheInterface $cache, // default cache.app
    ) {
    }

    public function get(string $scope, string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->cacheKey($scope, $key), function (ItemInterface $item) use ($scope, $key, $default): mixed {
            $item->expiresAfter(86400);
            $setting = $this->settings->findOne($scope, $key);
            return $setting?->value() ?? $default;
        });
    }

    public function set(string $scope, string $key, mixed $value, ?string $description = null): Setting
    {
        // ... persist ...
        $this->cache->delete($this->cacheKey($scope, $key));
        return $setting;
    }

    private function cacheKey(string $scope, string $key): string
    {
        return self::CACHE_PREFIX.$scope.'.'.$key;
    }
}
```

**Фактическое состояние** ([`CachedPublicPageResolver`](../src/Module/Content/Application/Service/CachedPublicPageResolver.php)) — отдельный пул `cache.public_page` подключается через `#[Autowire(service: 'cache.public_page')]`:

```php
#[AsDecorator(decorates: PublicPageResolverInterface::class)]
final class CachedPublicPageResolver implements PublicPageResolverInterface
{
    public const string CACHE_KEY_PREFIX = 'content.public_page.';
    public const int DEFAULT_TTL_SECONDS = 300;

    public function __construct(
        private readonly PublicPageResolverInterface $inner,
        #[Autowire(service: 'cache.public_page')]
        private readonly CacheInterface $cache,
    ) {}

    public function resolve(string $path): ?PublicPageView
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $key = PublicPageCacheKey::forPath($normalized);

        return $this->cache->get($key, function (ItemInterface $item) use ($normalized): ?PublicPageView {
            $item->expiresAfter(self::DEFAULT_TTL_SECONDS);
            return $this->inner->resolve($normalized);
        });
    }
}
```

**Целевое состояние** — миграция остальных сервисов (Settings, Menu, SEO sitemap chunks) на отдельные пулы по тому же шаблону.

Правила:

- Тип-хинт `Symfony\Contracts\Cache\CacheInterface`. Для конкретного пула — `#[Autowire(service: 'cache.<pool>')]` (для `Target` нужен alias, который Symfony cache framework для пулов **не** регистрирует).
- Не обращаться к файловой системе кэша напрямую (`file_put_contents` в `var/cache/...`) — только через `CacheInterface`/`TagAwareCacheInterface`.
- Ключ: единая утилита (`PublicPageCacheKey::forPath()`), а не сборка строк по месту. Хеш xxh128 от нормализованного пути защищает от длинных ключей и недопустимых для PSR-6 символов (`{}()/\\@:`).
- Кэшировать допустимо `null` (для предотвращения thundering herd на 404). Утилита резолвера это делает автоматически.

## Cache key conventions

| Префикс | Что |
|---|---|
| `content.public_page.<xxh128(path)>` | View model публичной страницы (`PublicPageCacheKey::forPath`) |
| `settings.<key>` | Setting value |
| `menu.<location>` | Меню |
| `seo.sitemap.<chunk>` | Sitemap chunk |
| `seo.robots` | robots.txt |
| `seo.redirect.<path>` | Resolved redirect |

## Инвалидация

### Публичные страницы (фактическое)

`PublicPageResolverInterface` декорируется `CachedPublicPageResolver`, который пишет/читает из `cache.public_page` (TTL 300 сек.).

Инвалидация — через [`PublicPageCacheInvalidator`](../src/Module/Content/Application/Service/PublicPageCacheInvalidator.php). Вызывается из всех Content-handler'ов, мутирующих публичный рендер:

| Handler | Что инвалидируется |
|---|---|
| `PublishPageHandler` | `page.path()` |
| `ArchivePageHandler` | `page.path()` |
| `UpdatePageHandler` | старый `path` + новый `path` (через `invalidateMany`) |
| `UpdatePageSeoMetadataHandler` | `page.path()` |
| `CreatePageBlockHandler` | `block.page().path()` |
| `UpdatePageBlockHandler` | `block.page().path()` |
| `DeletePageBlockHandler` | `block.page().path()` (читается до `remove()`) |
| `ReorderPageBlocksHandler` | `page.path()` |

`CreatePageHandler` инвалидацию не вызывает: новая страница рождается в `Draft` и попадает в публичный кэш только после `publish`.

TTL 300 секунд — safety net на случай пропущенного вызова инвалидатора. Для long-form статичного контента можно увеличить через `CachedPublicPageResolver::DEFAULT_TTL_SECONDS` (отдельный PR).

### Settings (фактическое)

При `set()`/`delete()` через [`SettingsService`](../src/Module/Settings/Application/Service/SettingsService.php) — сервис удаляет ключ `settings.<scope>.<key>` из default `cache.app`.

### Menu / Seo (целевое)

При изменении меню или SEO-сущностей — handler удаляет соответствующие ключи (`menu.<location>`, `seo.sitemap.*`, `seo.robots`, `seo.redirect.<path>`). Реализуется по мере появления модулей `Menu` и расширения модуля `Seo`.

### Tag-based invalidation

Пул `cache.public_page` tag-aware (`tags: true`), `PublicPageCacheInvalidator` помимо `delete()` вызывает `invalidateTags()` с тегом пути (`PublicPageCacheKey::tagForPath()`). Для сложных инвалидаций (например, удалить все `page.*`) — использовать `TagAwareCacheInterface` и теги `page`, `module:content`. Теги работают поверх файловых пулов; при очень большом числе записей инвалидация тега дешёвая (меняется версия тега), а физическое удаление происходит при `cache:pool:prune`.

## Stampede protection

`CacheInterface::get()` использует probabilistic early expiration; для критичных ресурсов добавлять `early_expiration_factor` или lock (`symfony/lock` с `FlockStore` — файловый, либо `PdoStore` поверх MySQL, если процессы работают на разных серверах). Сейчас `symfony/lock` в проекте не используется.

## Warmup

- `php bin/console cache:warmup` — система cache (Doctrine metadata, container).
- После release-деплоя `var/cache/<env>/pools/app` пуст (у релиза свой `var/cache`): первые запросы идут в БД, затем пулы наполняются.
- Целевое: `app:cache:warmup` — пройтись по опубликованным `Page`, прогреть `cache.public_page`.

## Cache clear

```bash
php bin/console cache:clear
make cache-clear
php bin/console cache:pool:clear cache.public_page   # один пул
php bin/console cache:pool:prune                      # удалить протухшие записи файловых пулов
```

В release-deploy — `cache:clear --env=prod --no-warmup` уже выполняется в скрипте.

## HTTP cache (целевое)

- Reverse proxy на Nginx (`proxy_cache`) для публичных страниц.
- `Cache-Control: public, max-age=...`, `ETag`/`Last-Modified`.
- Cache vary by language/headers — не используется (один язык).

## Что нельзя кешировать

- Содержимое admin API ответов (всегда live).
- Любые ответы с user-specific данными (admin shell, авторизованные).
- Стэйт сессии.
- Paginated списки с приватными фильтрами без учёта прав (риск утечки между ролями).

## Anti-patterns

- Кеширование с TTL «бесконечность» без явной инвалидации.
- Использование одного пула для всего — теряется контроль по lifecycle.
- Cache key с user-input без санитайза → cache poisoning.
- Сериализация Doctrine entity → проблема при загрузке lazy-relations.
- Полагаться на cache как на основное хранилище.
- Рассчитывать на общий кэш между серверами/релизами или на атомарные счётчики в `cache.app`.
- Хранить в кэше секреты и персональные данные с длинным TTL: файлы кэша лежат на диске в открытом (сериализованном) виде.

## Чек-лист новой кеш-обёртки

- [ ] Использован пул, соответствующий ресурсу (или создан новый).
- [ ] Тип-хинт `CacheInterface` (или `TagAwareCacheInterface` для tag-aware пула) + `#[Autowire(service: 'cache.<pool>')]`.
- [ ] Ключ детерминирован, без user-input без validator.
- [ ] Инвалидация прописана в соответствующем handler.
- [ ] TTL осмысленный, не «навсегда».
- [ ] Тест integration на cache hit/miss.
- [ ] Логирование `cache.error` при сбоях файлового кэша (нет прав на `var/cache`, нет места на диске); приложение продолжает работать с промахами кэша.

## Связанные документы

- [11-infrastructure-layer](11-infrastructure-layer.md)
- [13-front-area](13-front-area.md)
- [adr/0007-filesystem-cache-and-doctrine-messenger](adr/0007-filesystem-cache-and-doctrine-messenger.md)
- [37-runbooks](37-runbooks.md)
