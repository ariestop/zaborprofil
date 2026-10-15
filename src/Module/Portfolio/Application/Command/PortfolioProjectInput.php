<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Application\Command;

/**
 * Работа целиком из формы админки: поля, характеристики и упорядоченный список медиа.
 */
final readonly class PortfolioProjectInput
{
    /**
     * @param list<array{label: string, value: string}> $specs
     * @param list<PortfolioMediaInput>                 $media
     */
    public function __construct(
        public string $categoryId,
        public string $title,
        public string $slug,
        public ?string $place = null,
        public ?int $year = null,
        public ?string $summary = null,
        public string $description = '',
        public array $specs = [],
        public array $media = [],
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
    ) {
    }
}
