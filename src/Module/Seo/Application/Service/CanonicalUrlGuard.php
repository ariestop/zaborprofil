<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use InvalidArgumentException;

final readonly class CanonicalUrlGuard
{
    private string $siteHost;

    public function __construct(string $siteUrl)
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);

        if (!\is_string($host) || $host === '') {
            throw new InvalidArgumentException('SITE_URL must be an absolute URL with host.');
        }

        $this->siteHost = mb_strtolower($host);
    }

    public function assertAllowed(?string $canonicalUrl): void
    {
        if ($canonicalUrl === null || trim($canonicalUrl) === '') {
            return;
        }

        $host = parse_url($canonicalUrl, PHP_URL_HOST);

        if (!\is_string($host) || mb_strtolower($host) !== $this->siteHost) {
            throw new InvalidArgumentException('Canonical URL must belong to the configured SITE_URL host.');
        }
    }
}
