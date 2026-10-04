<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\ValueObject;

use App\Module\Content\Domain\Enum\PageStatus;

final readonly class PageTransition
{
    public function __construct(
        public PageStatus $from,
        public PageStatus $to,
    ) {
    }
}
