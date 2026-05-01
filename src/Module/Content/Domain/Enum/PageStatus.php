<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
