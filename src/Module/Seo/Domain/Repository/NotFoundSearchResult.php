<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

use App\Module\Seo\Domain\Entity\NotFoundLogEntry;

final readonly class NotFoundSearchResult
{
    /**
     * @param list<NotFoundLogEntry> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $totalHits,
    ) {
    }
}
