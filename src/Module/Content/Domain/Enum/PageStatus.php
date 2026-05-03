<?php

declare(strict_types=1);

namespace App\Module\Content\Domain\Enum;

enum PageStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
    case Deleted = 'deleted';

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }

    public function isEditableByDefault(): bool
    {
        return !\in_array($this, [self::Archived, self::Deleted], true);
    }

    public function isSitemapEligible(): bool
    {
        return $this === self::Published;
    }
}
