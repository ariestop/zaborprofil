<?php

declare(strict_types=1);

namespace App\Estimation\Application\UseCase;

use App\Estimation\Infrastructure\Persistence\Entity\Estimate;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GetEstimateUseCase
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array|null
     */
    public function execute(int $id): ?array
    {
        $estimate = $this->entityManager->getRepository(Estimate::class)->find($id);
        if (!$estimate) {
            return null;
        }

        $lines = [];
        foreach ($estimate->getLines() as $line) {
            $lines[] = [
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'price' => (float) $line->getPrice(),
            ];
        }

        return [
            'id' => $estimate->getId(),
            'totalPrice' => (float) $estimate->getTotalPrice(),
            'status' => $estimate->getStatus(),
            'expiresAt' => $estimate->getExpiresAt()?->format(\DateTimeInterface::ATOM),
            'lines' => $lines,
        ];
    }
}
