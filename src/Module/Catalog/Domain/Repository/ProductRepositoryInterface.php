<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Repository;

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
}
