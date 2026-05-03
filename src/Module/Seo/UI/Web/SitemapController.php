<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Web;

use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generates `/sitemap.xml` from every published, indexable page. The sitemap
 * is intentionally simple (urlset + url + loc + lastmod) so search engines
 * can ingest it without any extra dependency.
 */
final readonly class SitemapController
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private string $siteUrl,
    ) {
    }

    #[Route('/sitemap.xml', name: 'public_sitemap_xml', methods: ['GET'])]
    public function __invoke(): Response
    {
        $base = rtrim(trim($this->siteUrl), '/');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/0.9\">\n";

        foreach ($this->pages->findAllPublishedIndexable() as $page) {
            $loc = htmlspecialchars($base.$page->path(), \ENT_QUOTES | \ENT_XML1, 'UTF-8');
            $lastmod = $page->updatedAt()->format('Y-m-d');

            $xml .= "    <url>\n";
            $xml .= '        <loc>'.$loc."</loc>\n";
            $xml .= '        <lastmod>'.$lastmod."</lastmod>\n";
            $xml .= "    </url>\n";
        }

        $xml .= "</urlset>\n";

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
