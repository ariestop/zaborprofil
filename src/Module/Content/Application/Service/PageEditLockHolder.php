<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

final readonly class PageEditLockHolder
{
    public function __construct(
        public string $userId,
        public string $label,
        public string $sessionId,
        public int $acquiredAt,
        public int $heartbeatAt,
    ) {
    }
}
