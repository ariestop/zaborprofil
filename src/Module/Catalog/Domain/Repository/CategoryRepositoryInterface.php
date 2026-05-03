<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Repository;

use App\Module\Catalog\Domain\Entity\Category;

interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function remove(Category $category): void;

    public function get(string $id): Category;

    public function findById(?string $id): ?Category;

    /**
     * @return list<Category>
     */
    public function findAllForAdmin(): array;
}
