<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\ValueObject;

enum PageVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
    case Unlisted = 'unlisted';

    public function allowsIndexing(): bool
    {
        return $this === self::Public;
    }
}
