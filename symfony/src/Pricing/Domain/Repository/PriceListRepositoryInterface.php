<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Pricing\Infrastructure\Persistence\Entity\PriceList;

interface PriceListRepositoryInterface
{
    public function getDefaultPriceList(): ?PriceList;

    public function findByProfileId(int $profileId): ?PriceList;

    public function findById(int $id): ?PriceList;
}
