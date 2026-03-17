<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence;

use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Infrastructure\Persistence\Entity\PriceList;
use App\Pricing\Infrastructure\Persistence\Entity\PriceProfile;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePriceListRepository implements PriceListRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getDefaultPriceList(): ?PriceList
    {
        $profileRepo = $this->entityManager->getRepository(PriceProfile::class);
        $profile = $profileRepo->findOneBy(['type' => 'default'], ['id' => 'ASC']);

        if (!$profile) {
            return null;
        }

        return $this->entityManager->getRepository(PriceList::class)->findOneBy(
            ['priceProfileId' => $profile->getId()],
            ['id' => 'ASC']
        );
    }

    public function findByProfileId(int $profileId): ?PriceList
    {
        return $this->entityManager->getRepository(PriceList::class)->findOneBy(
            ['priceProfileId' => $profileId],
            ['id' => 'ASC']
        );
    }

    public function findById(int $id): ?PriceList
    {
        return $this->entityManager->getRepository(PriceList::class)->find($id);
    }
}
