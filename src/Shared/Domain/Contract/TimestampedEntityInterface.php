<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contract;

interface TimestampedEntityInterface
{
    public function initializeTimestamps(): void;

    public function touch(): void;
}
