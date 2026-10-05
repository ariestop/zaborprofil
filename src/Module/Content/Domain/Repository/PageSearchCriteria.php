<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Repository;

use App\Module\Content\Domain\Enum\PageStatus;

final readonly class PageSearchCriteria
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        public ?string $query = null,
        public ?PageStatus $status = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }

    public function normalizedPage(): int
    {
        return max(1, $this->page);
    }

    public function normalizedPerPage(): int
    {
        return min(self::MAX_PER_PAGE, max(1, $this->perPage));
    }
}
