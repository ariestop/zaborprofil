<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Query;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Repository\PortfolioCategoryRepositoryInterface;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;

/**
 * Чтение портфолио для сайта: только опубликованные работы, разделы — только непустые.
 */
final readonly class PublicPortfolioQuery
{
    public function __construct(
        private PortfolioCategoryRepositoryInterface $categories,
        private PortfolioProjectRepositoryInterface $projects,
    ) {
    }

    /**
     * Непустые разделы по порядку из админки.
     *
     * @return list<PortfolioCategoryCard>
     */
    public function categoryCards(): array
    {
        $counts = $this->projects->countPublishedByCategory();
        $cards = [];
        foreach ($this->categories->findAllOrdered() as $category) {
            $count = $counts[(string) $category->id()] ?? 0;
            if ($count === 0) {
                continue;
            }
            $first = $this->projects->findPublishedByCategory($category)[0] ?? null;
            $cards[] = new PortfolioCategoryCard($category, $count, $first?->cover()?->src());
        }

        return $cards;
    }

    public function totalPublished(): int
    {
        return $this->projects->countPublished();
    }

    /**
     * @return list<PortfolioProject>
     */
    public function latest(int $limit, ?PortfolioCategory $category = null): array
    {
        return $this->projects->findLatestPublished($limit, $category);
    }

    /**
     * Работы для блока «Портфолио» в конструкторе страниц: выбранные вручную (в их порядке) или новые.
     *
     * @param list<string> $ids
     *
     * @return list<PortfolioProject>
     */
    public function forBlock(array $ids, ?string $categorySlug, int $limit): array
    {
        if ($ids !== []) {
            return \array_slice($this->projects->findPublishedByIds($ids), 0, $limit);
        }

        $category = $categorySlug === null || $categorySlug === '' ? null : $this->categories->findBySlug($categorySlug);
        if ($categorySlug !== null && $categorySlug !== '' && !$category instanceof PortfolioCategory) {
            return [];
        }

        return $this->projects->findLatestPublished($limit, $category);
    }

    public function category(string $slug): ?PortfolioCategory
    {
        return $this->categories->findBySlug($slug);
    }

    /**
     * @return list<PortfolioProject>
     */
    public function projectsOf(PortfolioCategory $category): array
    {
        return $this->projects->findPublishedByCategory($category);
    }

    public function project(PortfolioCategory $category, string $slug): ?PortfolioProject
    {
        return $this->projects->findPublishedBySlug($category, $slug);
    }

    /**
     * Соседние работы раздела по порядку, по кругу: у единственной работы соседей нет.
     *
     * @return array{previous: ?PortfolioProject, next: ?PortfolioProject}
     */
    public function neighbours(PortfolioProject $project): array
    {
        $list = $this->projects->findPublishedByCategory($project->category());
        $count = \count($list);
        if ($count < 2) {
            return ['previous' => null, 'next' => null];
        }

        $index = 0;
        foreach ($list as $position => $candidate) {
            if ($candidate->id()->equals($project->id())) {
                $index = $position;
            }
        }

        $previous = $list[($index - 1 + $count) % $count];
        $next = $list[($index + 1) % $count];

        return ['previous' => $previous, 'next' => $count === 2 && $previous === $next ? null : $next];
    }
}
