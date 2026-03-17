<?php

declare(strict_types=1);

namespace App\Configurator\Domain\Service;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Configurator\Application\DTO\CalculationResult;
use App\Configurator\Application\DTO\ConfigurationDTO;
use App\Pricing\Domain\Repository\PriceListRepositoryInterface;
use App\Pricing\Domain\Repository\PriceRuleRepositoryInterface;

final readonly class CalculationEngine
{
    private const CURRENCY = 'RUB';

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private PriceListRepositoryInterface $priceListRepository,
        private PriceRuleRepositoryInterface $priceRuleRepository,
    ) {
    }

    public function calculate(ConfigurationDTO $config, ?int $priceProfileId = null): CalculationResult
    {
        $priceList = $priceProfileId
            ? $this->priceListRepository->findByProfileId($priceProfileId)
            : $this->priceListRepository->getDefaultPriceList();

        if (!$priceList) {
            return new CalculationResult(0, self::CURRENCY, [], [], []);
        }

        $product = $this->productRepository->findById($config->productId);
        if (!$product) {
            return new CalculationResult(0, self::CURRENCY, [], [], []);
        }

        $priceRule = $this->priceRuleRepository->findByProductAndPriceList(
            $config->productId,
            $priceList->getId()
        );

        $basePrice = $priceRule ? (float) $priceRule->getBasePrice() : 0.0;

        $breakdown = [];
        $materials = [];
        $services = [];

        // Apply quantity
        $lineTotal = $basePrice * $config->quantity;
        $breakdown[] = [
            'description' => $product->getName(),
            'quantity' => $config->quantity,
            'unitPrice' => round($basePrice, 2),
            'total' => round($lineTotal, 2),
        ];

        // Apply options (e.g. height, width multipliers)
        $optionsMultiplier = 1.0;
        if (isset($config->options['height']) && isset($config->options['width'])) {
            $area = ($config->options['height'] / 1000) * ($config->options['width'] / 1000);
            $optionsMultiplier = $area;
            $materials[] = [
                'name' => 'Panel area',
                'quantity' => round($area, 2),
                'unit' => 'm²',
            ];
        }

        $totalWithOptions = $lineTotal * $optionsMultiplier;

        // Extras (e.g. installation)
        foreach ($config->extras as $extra) {
            $extraPrice = $this->getExtraPrice($extra);
            $services[] = ['name' => $extra, 'price' => round($extraPrice, 2)];
            $totalWithOptions += $extraPrice;
        }

        return new CalculationResult(
            total: round($totalWithOptions, 2),
            currency: $priceList->getCurrency() ?? self::CURRENCY,
            breakdown: $breakdown,
            materials: $materials,
            services: $services,
        );
    }

    private function getExtraPrice(string $extra): float
    {
        return match (strtolower($extra)) {
            'installation' => 45000.0,
            'delivery' => 5000.0,
            default => 0.0,
        };
    }
}
