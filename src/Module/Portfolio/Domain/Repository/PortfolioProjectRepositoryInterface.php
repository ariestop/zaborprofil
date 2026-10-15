<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;

interface PortfolioProjectRepositoryInterface
{
    public function save(PortfolioProject $project): void;

    public function remove(PortfolioProject $project): void;

    public function get(string $id): PortfolioProject;

    /**
     * @return list<PortfolioProject> все работы: по категориям, внутри — по sortOrder
     */
    public function findAllForAdmin(): array;

    /**
     * @return list<PortfolioProject>
     */
    public function findByCategory(PortfolioCategory $category): array;

    /**
     * @return list<PortfolioProject> опубликованные работы категории по sortOrder
     */
    public function findPublishedByCategory(PortfolioCategory $category): array;

    public function findPublishedBySlug(PortfolioCategory $category, string $slug): ?PortfolioProject;

    /**
     * @return list<PortfolioProject> новые опубликованные работы (по дате публикации)
     */
    public function findLatestPublished(int $limit, ?PortfolioCategory $category = null): array;

    /**
     * @param list<string> $ids
     *
     * @return list<PortfolioProject> опубликованные работы в порядке $ids
     */
    public function findPublishedByIds(array $ids): array;

    /**
     * @return array<string, int> id категории => число опубликованных работ
     */
    public function countPublishedByCategory(): array;

    public function countPublished(): int;

    /**
     * @return list<PortfolioProject>
     */
    public function findPublishedSlice(int $limit, int $offset): array;

    public function slugTaken(PortfolioCategory $category, string $slug, ?string $exceptId): bool;

    public function countByCategory(PortfolioCategory $category): int;
}
