<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\ValueObject;

use App\Module\Media\Domain\Entity\MediaAsset;

final readonly class MediaAssetPage
{
    /**
     * @param list<MediaAsset> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
