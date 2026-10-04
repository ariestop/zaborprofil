<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Repository;

use App\Module\Lead\Domain\Entity\Lead;

final readonly class LeadSearchResult
{
    /**
     * @param list<Lead> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
