<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Entity\Page;

final readonly class PageSearchResult
{
    /**
     * @param list<Page> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
