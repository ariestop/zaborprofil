<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Infrastructure\Sitemap;

use App\Module\Portfolio\Application\Query\PublicPortfolioQuery;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;
use App\Shared\Application\Sitemap\SitemapUrl;
use App\Shared\Application\Sitemap\SitemapUrlSourceInterface;
use DateTimeImmutable;

/**
 * Портфолио в sitemap.xml: /portfolio/, непустые разделы и опубликованные работы.
 * Работ немного (сотни), поэтому список строится целиком и режется на нужный кусок.
 */
final readonly class PortfolioSitemapSource implements SitemapUrlSourceInterface
{
    public function __construct(
        private PublicPortfolioQuery $portfolio,
        private PortfolioProjectRepositoryInterface $projects,
    ) {
    }

    public function countUrls(): int
    {
        $projects = $this->projects->countPublished();

        return $projects === 0 ? 0 : 1 + \count($this->portfolio->categoryCards()) + $projects;
    }

    public function urls(int $limit, int $offset): array
    {
        return \array_slice($this->all(), $offset, $limit);
    }

    /**
     * @return list<SitemapUrl>
     */
    private function all(): array
    {
        $projects = $this->projects->findPublishedSlice(PHP_INT_MAX, 0);
        if ($projects === []) {
            return [];
        }

        $latest = new DateTimeImmutable('@0');
        $byCategory = [];
        foreach ($projects as $project) {
            $updated = $project->updatedAt();
            $key = (string) $project->category()->id();
            $latest = max($latest, $updated);
            $byCategory[$key] = max($byCategory[$key] ?? $updated, $updated);
        }

        $urls = [new SitemapUrl('/portfolio/', $latest)];
        foreach ($this->portfolio->categoryCards() as $card) {
            $urls[] = new SitemapUrl($card->category->path(), $byCategory[(string) $card->category->id()] ?? $latest);
        }
        foreach ($projects as $project) {
            $urls[] = new SitemapUrl($project->path(), $project->updatedAt());
        }

        return $urls;
    }
}
