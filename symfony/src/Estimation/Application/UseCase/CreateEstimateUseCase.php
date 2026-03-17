<?php

declare(strict_types=1);

namespace App\Estimation\Application\UseCase;

use App\Configurator\Application\DTO\CalculationResult;
use App\Configurator\Application\DTO\ConfigurationDTO;
use App\Configurator\Domain\Service\CalculationEngine;
use App\Configurator\Infrastructure\Persistence\Entity\Configuration;
use App\Estimation\Infrastructure\Persistence\Entity\Estimate;
use App\Estimation\Infrastructure\Persistence\Entity\EstimateLine;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreateEstimateUseCase
{
    public function __construct(
        private CalculationEngine $calculationEngine,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{estimateId: int, total: float, lines: array}
     */
    public function execute(ConfigurationDTO $config, ?string $guestToken = null, ?int $userId = null): array
    {
        $calculation = $this->calculationEngine->calculate($config, null);

        $configuration = new Configuration();
        $configuration->setConfigData([
            'productId' => $config->productId,
            'variantId' => $config->variantId,
            'quantity' => $config->quantity,
            'options' => $config->options,
            'extras' => $config->extras,
        ]);
        $configuration->setGuestToken($guestToken);
        $configuration->setUserId($userId);

        $this->entityManager->persist($configuration);
        $this->entityManager->flush();

        $estimate = new Estimate();
        $estimate->setConfigurationId($configuration->getId());
        $estimate->setTotalPrice((string) $calculation->total);
        $estimate->setStatus('draft');
        $estimate->setExpiresAt((new \DateTimeImmutable())->modify('+30 days'));
        $estimate->setUserId($userId);

        foreach ($calculation->breakdown as $line) {
            $estimateLine = new EstimateLine();
            $estimateLine->setDescription($line['description']);
            $estimateLine->setQuantity($line['quantity']);
            $estimateLine->setPrice((string) $line['total']);
            $estimate->addLine($estimateLine);
        }

        foreach ($calculation->services as $service) {
            $estimateLine = new EstimateLine();
            $estimateLine->setDescription($service['name']);
            $estimateLine->setQuantity(1);
            $estimateLine->setPrice((string) $service['price']);
            $estimate->addLine($estimateLine);
        }

        $this->entityManager->persist($estimate);
        $this->entityManager->flush();

        $lines = [];
        foreach ($estimate->getLines() as $line) {
            $lines[] = [
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'price' => (float) $line->getPrice(),
            ];
        }

        return [
            'estimateId' => $estimate->getId(),
            'total' => $calculation->total,
            'currency' => $calculation->currency,
            'lines' => $lines,
        ];
    }
}
