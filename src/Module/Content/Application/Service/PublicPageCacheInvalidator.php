<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

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
 */
final readonly class PublicPageCacheInvalidator
{
    public function __construct(
        #[Autowire(service: 'cache.public_page')]
        private TagAwareCacheInterface $cache,
        private PublicHttpCachePurgerInterface $httpCachePurger,
    ) {
    }

    public function invalidate(string $path): void
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $this->cache->delete(PublicPageCacheKey::forPath($normalized));
        $this->cache->invalidateTags([PublicPageCacheKey::tagForPath($normalized)]);
        $this->httpCachePurger->purgePath($normalized);
    }

    /**
     * @param iterable<string> $paths
     */
    public function invalidateMany(iterable $paths): void
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

    public function invalidateAll(): void
    {
        $this->cache->invalidateTags([PublicPageCacheKey::globalTag()]);
        $this->httpCachePurger->purgeAll();
    }
}
