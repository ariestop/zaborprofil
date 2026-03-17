<?php

declare(strict_types=1);

namespace App\Configurator\Application\DTO;

final readonly class CalculationResult
{
    /**
     * @param array<array{description: string, quantity: int, unitPrice: float, total: float}> $breakdown
     * @param array<array{name: string, quantity: float, unit: string}> $materials
     * @param array<array{name: string, price: float}> $services
     */
    public function __construct(
        public float $total,
        public string $currency,
        public array $breakdown,
        public array $materials,
        public array $services,
    ) {
    }

    public function toArray(): array
    {
        return [
            'total' => round($this->total, 2),
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
            'materials' => $this->materials,
            'services' => $this->services,
        ];
    }
}
