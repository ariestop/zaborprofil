<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Command;

final readonly class PortfolioCategoryInput
{
    public function __construct(
        public string $title,
        public string $slug,
        public ?string $shortTitle = null,
        public ?string $description = null,
    ) {
    }
}
