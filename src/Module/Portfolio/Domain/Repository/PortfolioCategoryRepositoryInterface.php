<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;

interface PortfolioCategoryRepositoryInterface
{
    public function save(PortfolioCategory $category): void;

    public function remove(PortfolioCategory $category): void;

    public function get(string $id): PortfolioCategory;

    /**
     * @return list<PortfolioCategory> по порядку sortOrder
     */
    public function findAllOrdered(): array;

    public function findBySlug(string $slug): ?PortfolioCategory;
}
