<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Shared\Application\Transaction\TransactionRunnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Invalidates {@see CachedPublicPageResolver} entries when Content handlers
 * mutate a {@see \App\Module\Content\Domain\Entity\Page} or its blocks.
 *
 * Always invoke for **both** the previous and the new path on update, because
 * a path change leaves a stale entry under the previous key.
 *
 * Вместе с кэшем резолвера сбрасывает HTTP-кэш веб-сервера ({@see PublicHttpCachePurgerInterface}).
 *
 * Внутри транзакции сброс откладывается до её фиксации: иначе параллельный запрос между сбросом и COMMIT
 * прочитал бы старые данные и снова положил их в кэш. При откате кэш не трогается.
 */
final readonly class PublicPageCacheInvalidator
{
    public function __construct(
        #[Autowire(service: 'cache.public_page')]
        private TagAwareCacheInterface $cache,
        private PublicHttpCachePurgerInterface $httpCachePurger,
        private ?TransactionRunnerInterface $transactions = null,
    ) {
    }

    public function invalidate(string $path): void
    {
        $this->afterCommit(function () use ($path): void {
            $normalized = PublicPagePathNormalizer::normalize($path);
            $this->cache->delete(PublicPageCacheKey::forPath($normalized));
            $this->cache->invalidateTags([PublicPageCacheKey::tagForPath($normalized)]);
            $this->httpCachePurger->purgePath($normalized);
        });
    }

    /**
     * @param iterable<string> $paths
     */
    public function invalidateMany(iterable $paths): void
    {
        $paths = iterator_to_array($paths, false);
        $this->afterCommit(function () use ($paths): void {
            $this->purgeMany($paths);
        });
    }

    public function invalidateAll(): void
    {
        $this->afterCommit(function (): void {
            $this->cache->invalidateTags([PublicPageCacheKey::globalTag()]);
            $this->httpCachePurger->purgeAll();
        });
    }

    /**
     * @param list<string> $paths
     */
    private function purgeMany(array $paths): void
    {
        $seen = [];

        foreach ($paths as $path) {
            $normalized = PublicPagePathNormalizer::normalize($path);

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $this->cache->delete(PublicPageCacheKey::forPath($normalized));
            $this->cache->invalidateTags([PublicPageCacheKey::tagForPath($normalized)]);
            $this->httpCachePurger->purgePath($normalized);
        }
    }

    /**
     * @param callable(): void $purge
     */
    private function afterCommit(callable $purge): void
    {
        if ($this->transactions === null) {
            $purge();

            return;
        }

        $this->transactions->afterCommit($purge);
    }
}
