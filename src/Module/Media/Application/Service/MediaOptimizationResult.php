<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Service;

final readonly class MediaOptimizationResult
{
    /**
     * @param list<array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}> $variants
     */
    public function __construct(
        public int $size,
        public ?int $width,
        public ?int $height,
        public array $variants,
    ) {
    }
}
