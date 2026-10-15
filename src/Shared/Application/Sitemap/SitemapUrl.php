<?php

declare(strict_types=1);

namespace App\Shared\Application\Sitemap;

use DateTimeImmutable;

/**
 * Адрес для sitemap.xml: путь на сайте и дата последнего изменения.
 */
final readonly class SitemapUrl
{
    public function __construct(public string $path, public DateTimeImmutable $lastModified)
    {
    }
}
