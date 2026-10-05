<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

use App\Module\Seo\Domain\Entity\Redirect;

final readonly class RedirectSearchResult
{
    /**
     * @param list<Redirect> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
