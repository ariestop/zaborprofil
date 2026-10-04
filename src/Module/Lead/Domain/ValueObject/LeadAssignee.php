<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

final readonly class LeadAssignee
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }
}
