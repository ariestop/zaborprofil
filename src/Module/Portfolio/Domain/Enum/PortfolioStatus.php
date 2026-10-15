<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Domain\Enum;

enum PortfolioStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
