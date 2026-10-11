<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

/**
 * Centralizes cache key generation for the public page resolver. Keeps the
 * decorator and the invalidator in lockstep — never construct a key manually
 * elsewhere.
 */
final class PublicPageCacheKey
{
    // Bump the version whenever PublicPageView changes shape or meaning: the cache holds serialized objects.
    // v3: адрес из живой страницы, видимость (скрытая — 404, «по ссылке» — noindex).
    public const string PREFIX = 'content.public_page.v3.';

    public static function forPath(string $normalizedPath): string
    {
        return self::PREFIX . hash('xxh128', $normalizedPath);
    }

    public static function globalTag(): string
    {
        return self::PREFIX . 'all';
    }

    public static function tagForPath(string $normalizedPath): string
    {
        return self::PREFIX . 'path.' . hash('xxh128', $normalizedPath);
    }
}
