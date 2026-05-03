<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use InvalidArgumentException;

final readonly class SitemapBuilder
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private string $siteUrl,
        private int $chunkSize,
    ) {
        if ($this->chunkSize < 1) {
            throw new InvalidArgumentException('Sitemap chunk size must be greater than zero.');
        }
    }

    public function chunkCount(): int
    {
        return (int) ceil($this->pages->countPublishedIndexable() / $this->chunkSize);
    }

    public function buildRootSitemap(): string
    {
        if ($this->chunkCount() <= 1) {
            return $this->buildPageChunk(1);
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        for ($page = 1; $page <= $this->chunkCount(); ++$page) {
            $loc = $this->xml($this->absoluteUrl('/sitemap-pages-'.$page.'.xml'));
            $xml .= "    <sitemap>\n";
            $xml .= '        <loc>'.$loc."</loc>\n";
            $xml .= "    </sitemap>\n";
        }

        $xml .= "</sitemapindex>\n";

        return $xml;
    }

    public function buildPageChunk(int $pageNumber): string
    {
        $offset = ($pageNumber - 1) * $this->chunkSize;
        $pages = $this->pages->findPublishedIndexableSlice($this->chunkSize, $offset);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($pages as $page) {
            $xml .= $this->urlEntry($page);
        }

        $xml .= "</urlset>\n";

        return $xml;
    }

    private function urlEntry(Page $page): string
    {
        $loc = $this->xml($this->absoluteUrl($page->path()));
        $lastmod = $page->updatedAt()->format('Y-m-d');

        return "    <url>\n"
            .'        <loc>'.$loc."</loc>\n"
            .'        <lastmod>'.$lastmod."</lastmod>\n"
            ."    </url>\n";
    }

    private function absoluteUrl(string $path): string
    {
        return rtrim(trim($this->siteUrl), '/').'/'.ltrim($path, '/');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}
