<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Query;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;

/**
 * Раздел портфолио для плиток и переключателя разделов: число опубликованных работ и обложка первой из них.
 */
final readonly class PortfolioCategoryCard
{
    public function __construct(
        public PortfolioCategory $category,
        public int $count,
        public ?string $cover,
    ) {
    }
}
