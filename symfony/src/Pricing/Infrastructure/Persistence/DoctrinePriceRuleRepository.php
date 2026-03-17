<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence;

use App\Pricing\Domain\Repository\PriceRuleRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Entity\PriceRule;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePriceRuleRepository implements PriceRuleRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findByProductAndPriceList(int $productId, int $priceListId): ?PriceRule
    {
        return $this->entityManager->getRepository(PriceRule::class)->findOneBy([
            'productId' => $productId,
            'priceListId' => $priceListId,
        ]);
    }
}
