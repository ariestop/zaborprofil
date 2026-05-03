<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

/**
 * Single source of truth for public page path normalization. Every consumer
 * (resolver, cache key builder, invalidator, view) must produce the same
 * canonical form so that cache writes and reads agree.
 */
final class PublicPagePathNormalizer
{
    public static function normalize(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return '/';
        }

        if (!str_starts_with($normalized, '/')) {
            $normalized = '/' . $normalized;
        }

        return $normalized;
    }
}
