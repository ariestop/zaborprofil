<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Web;

use App\Module\Seo\Application\Service\SitemapBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generates sitemap index/chunks from published, indexable pages.
 */
final readonly class SitemapController
{
    public function __construct(private SitemapBuilder $sitemap)
    {
    }

    #[Route('/sitemap.xml', name: 'public_sitemap_xml', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->xmlResponse($this->sitemap->buildRootSitemap());
    }

    #[Route('/sitemap-pages-{page}.xml', name: 'public_sitemap_page_xml', requirements: ['page' => '[1-9]\d*'], methods: ['GET'])]
    public function page(int $page): Response
    {
        if ($page > $this->sitemap->chunkCount()) {
            return new Response('Sitemap chunk not found.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
        }

        return $this->xmlResponse($this->sitemap->buildPageChunk($page));
    }

    private function xmlResponse(string $xml): Response
    {
        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
