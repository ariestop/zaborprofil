<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Infrastructure\Persistence\Entity\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    /**
     * @return Product[]
     */
    public function findAllWithCategories(int $page = 1, int $limit = 20): array;

    public function countAll(): int;
}
