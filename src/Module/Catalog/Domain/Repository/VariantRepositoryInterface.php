<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Repository;

use App\Module\Catalog\Domain\Entity\Variant;

interface VariantRepositoryInterface
{
    public function save(Variant $variant): void;

    public function remove(Variant $variant): void;

    public function get(string $id): Variant;
}
