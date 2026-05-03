<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Caches resolved {@see PublicPageView} per normalized path.
 *
 * The cache stores `?PublicPageView` (null markers prevent thundering herd on
 * 404s). TTL is intentionally short (5 minutes) because cache invalidation in
 * Content handlers covers the happy path; TTL is the safety net.
 */
#[AsDecorator(decorates: PublicPageResolverInterface::class)]
final readonly class CachedPublicPageResolver implements PublicPageResolverInterface
{
    public const string CACHE_KEY_PREFIX = 'content.public_page.';
    public const int DEFAULT_TTL_SECONDS = 300;

    public function __construct(
        private PublicPageResolverInterface $inner,
        #[Autowire(service: 'cache.public_page')]
        private TagAwareCacheInterface $cache,
    ) {
    }

    public function resolve(string $path): ?PublicPageView
    {
        $normalized = PublicPagePathNormalizer::normalize($path);
        $key = PublicPageCacheKey::forPath($normalized);

        return $this->cache->get($key, function (ItemInterface $item) use ($normalized): ?PublicPageView {
            $item->expiresAfter(self::DEFAULT_TTL_SECONDS);
            $item->tag([PublicPageCacheKey::globalTag(), PublicPageCacheKey::tagForPath($normalized)]);

            return $this->inner->resolve($normalized);
        });
    }
}
