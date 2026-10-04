<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\ValueObject;

final readonly class LeadActor
{
    public function __construct(
        public ?string $id,
        public ?string $label,
    ) {
    }

    public static function system(): self
    {
        return new self(null, null);
    }
}
