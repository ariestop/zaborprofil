<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

final readonly class LeadEventType
{
    public const string STATUS_CHANGED = 'status_changed';
    public const string NOTE = 'note';
    public const string ASSIGNED = 'assigned';
}
