# 23. Cache и Redis

## Стек

- Redis 8.
- `predis/predis` 2.x.
- Symfony Cache (`cache.adapter.redis`).

## Конфигурация

`config/packages/cache.yaml`:

```yaml
framework:
    cache:
        app: cache.adapter.redis
        system: cache.adapter.system
        default_redis_provider: '%env(REDIS_URL)%'
        pools:
            cache.public_page:
                adapter: cache.app
                default_lifetime: 3600
            cache.settings:
                adapter: cache.app
                default_lifetime: 86400
            cache.menu:
                adapter: cache.app
                default_lifetime: 86400
            cache.seo:
                adapter: cache.app
                default_lifetime: 86400
```

В `test` все пулы — `cache.adapter.array` (быстрее, изолирует тесты).

## Пулы

| Пул | TTL | Что хранится |
|---|---|---|
| `cache.app` | n/a (общий) | Doctrine result cache (в `prod`), generic prewarm |
| `cache.system` | n/a | Symfony system cache (PHP arrays) |
| `cache.public_page` | 3600 | HTML/view-model публичных страниц по `Page.path` |
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
- Не использовать `Predis\Client` напрямую в Application.
- Ключ: единая утилита (`PublicPageCacheKey::forPath()`), а не сборка строк по месту. Хеш xxh128 от нормализованного пути защищает от длинных/невалидных Redis-ключей.
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

### Tag-based invalidation (целевое)

Symfony Cache поддерживает теги. Для сложных инвалидаций (например, удалить все `page.*`) — использовать TaggableCacheAdapter и теги `page`, `module:content`.

## Stampede protection

`CacheInterface::get()` использует probabilistic early expiration; для критичных ресурсов добавлять `early_expiration_factor` или внешний lock (`symfony/lock` через Redis).

## Warmup

- `php bin/console cache:warmup` — система cache (Doctrine metadata, container).
- Целевое: `app:cache:warmup` — пройтись по опубликованным `Page`, прогреть `cache.public_page`.

## Cache clear

```bash
php bin/console cache:clear
make cache-clear
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

## Чек-лист новой кеш-обёртки

- [ ] Использован пул, соответствующий ресурсу (или создан новый).
- [ ] Тип-хинт `CacheInterface` + `#[Target(...)]`.
- [ ] Ключ детерминирован, без user-input без validator.
- [ ] Инвалидация прописана в соответствующем handler.
- [ ] TTL осмысленный, не «навсегда».
- [ ] Тест integration на cache hit/miss.
- [ ] Логирование `cache.error` при сбоях Redis.

## Связанные документы

- [11-infrastructure-layer](11-infrastructure-layer.md)
- [13-front-area](13-front-area.md)
- [adr/0007-redis-cache-and-messenger](adr/0007-redis-cache-and-messenger.md)
- [37-runbooks](37-runbooks.md)
