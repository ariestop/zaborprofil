<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Web;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves a robots.txt response that depends on the current Symfony
 * environment.
 *
 * - prod  → allow indexing of public pages, block /admin/ and /api/, point
 *           to sitemap.xml
 * - other → block everything (dev, staging, test). Staging Nginx ALSO
 *           serves its own /robots.txt block as defence in depth.
 */
final readonly class RobotsController
{
    public function __construct(
        private string $environment,
        private string $siteUrl,
    ) {
    }

    #[Route('/robots.txt', name: 'public_robots_txt', methods: ['GET'])]
    public function __invoke(): Response
    {
        $body = $this->buildBody();

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function buildBody(): string
    {
        if ('prod' !== $this->environment) {
            return "User-agent: *\nDisallow: /\n";
        }

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /api/',
        ];

        $sitemapUrl = $this->buildSitemapUrl();
        if (null !== $sitemapUrl) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.$sitemapUrl;
        }

        return implode("\n", $lines)."\n";
    }

    private function buildSitemapUrl(): ?string
    {
        $base = trim($this->siteUrl);
        if ('' === $base) {
            return null;
        }

        return rtrim($base, '/').'/sitemap.xml';
    }
}
