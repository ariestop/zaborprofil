<?php

declare(strict_types=1);

namespace App\Module\Seo\Application\Service;

use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Sitemap\SitemapUrl;
use App\Shared\Application\Sitemap\SitemapUrlSourceInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * sitemap.xml: опубликованные страницы, товары и адреса модулей-источников ({@see SitemapUrlSourceInterface},
 * например портфолио). Все адреса идут единым списком и режутся на части по `chunkSize`.
 */
final readonly class SitemapBuilder
{
    /**
     * @var list<SitemapUrlSourceInterface>
     */
    private array $sources;

    /**
     * @param iterable<SitemapUrlSourceInterface> $sources
     */
    public function __construct(
        private PageRepositoryInterface $pages,
        private ProductRepositoryInterface $products,
        private string $siteUrl,
        private int $chunkSize,
        #[AutowireIterator(SitemapUrlSourceInterface::TAG)]
        iterable $sources = [],
    ) {
        if ($this->chunkSize < 1) {
            throw new InvalidArgumentException('Sitemap chunk size must be greater than zero.');
        }

        $this->sources = \is_array($sources) ? array_values($sources) : iterator_to_array($sources, false);
    }

    public function chunkCount(): int
    {
        $total = 0;
        foreach ($this->segments() as [$count]) {
            $total += $count;
        }

        return (int) ceil($total / $this->chunkSize);
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
        $limit = $this->chunkSize;
        $urls = [];

        foreach ($this->segments() as [$count, $fetch]) {
            if ($limit === 0) {
                break;
            }
            if ($offset >= $count) {
                $offset -= $count;

                continue;
            }

            $batch = $fetch(min($limit, $count - $offset), $offset);
            $urls = [...$urls, ...$batch];
            $limit -= \count($batch);
            $offset = 0;
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= $this->urlEntry($url);
        }

        $xml .= "</urlset>\n";

        return $xml;
    }

    /**
     * Части списка по порядку: количество адресов и функция выборки (limit, offset).
     *
     * @return list<array{0: int, 1: callable(int, int): list<SitemapUrl>}>
     */
    private function segments(): array
    {
        $segments = [
            [
                $this->pages->countPublishedIndexable(),
                fn (int $limit, int $offset): array => array_map(
                    static fn (Page $page): SitemapUrl => new SitemapUrl($page->path(), $page->updatedAt()),
                    $this->pages->findPublishedIndexableSlice($limit, $offset),
                ),
            ],
            [
                $this->products->countPublishedIndexable(),
                fn (int $limit, int $offset): array => array_map(
                    static fn (Product $product): SitemapUrl => new SitemapUrl($product->path(), $product->updatedAt()),
                    $this->products->findPublishedIndexableSlice($limit, $offset),
                ),
            ],
        ];

        foreach ($this->sources as $source) {
            $segments[] = [$source->countUrls(), $source->urls(...)];
        }

        return $segments;
    }

    private function urlEntry(SitemapUrl $url): string
    {
        return "    <url>\n"
            .'        <loc>'.$this->xml($this->absoluteUrl($url->path))."</loc>\n"
            .'        <lastmod>'.$url->lastModified->format('Y-m-d')."</lastmod>\n"
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
