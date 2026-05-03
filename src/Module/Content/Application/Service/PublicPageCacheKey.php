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
    public const string PREFIX = 'content.public_page.';

    public static function forPath(string $normalizedPath): string
    {
        return self::PREFIX . hash('xxh128', $normalizedPath);
    }
}
