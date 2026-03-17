<?php

declare(strict_types=1);

namespace App\Configurator\Application\DTO;

final readonly class ConfigurationDTO
{
    public function __construct(
        public int $productId,
        public int $variantId,
        public int $quantity,
        public array $options = [],
        public array $extras = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) ($data['productId'] ?? $data['product_id'] ?? 0),
            variantId: (int) ($data['variantId'] ?? $data['variant_id'] ?? 0),
            quantity: max(1, (int) ($data['quantity'] ?? 1)),
            options: $data['options'] ?? [],
            extras: $data['extras'] ?? [],
        );
    }
}
