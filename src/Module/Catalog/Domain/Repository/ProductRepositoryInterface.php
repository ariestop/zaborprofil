<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Repository;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function remove(Product $product): void;

    public function get(string $id): Product;

    /**
     * @return list<Product>
     */
    public function findAllForAdmin(): array;

    public function findPublishedByPath(string $path): ?Product;

    /**
     * @return list<Product>
     */
    public function findPublishedByCategory(?Category $category): array;

    public function countPublishedIndexable(): int;

    /**
     * @return list<Product>
     */
    public function findPublishedIndexableSlice(int $limit, int $offset): array;
}
