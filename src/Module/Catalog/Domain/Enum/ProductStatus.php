<?php

declare(strict_types=1);

namespace App\Module\Catalog\Domain\Enum;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
