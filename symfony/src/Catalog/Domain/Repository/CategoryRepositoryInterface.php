<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Infrastructure\Persistence\Entity\Category;

interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function findAll(): array;
}
