<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Pricing\Infrastructure\Persistence\Entity\PriceRule;

interface PriceRuleRepositoryInterface
{
    public function findByProductAndPriceList(int $productId, int $priceListId): ?PriceRule;
}
