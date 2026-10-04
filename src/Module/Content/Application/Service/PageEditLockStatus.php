<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use DateTimeImmutable;
use DateTimeInterface;

final readonly class PageEditLockStatus
{
    public function __construct(
        public ?PageEditLockHolder $holder,
        public bool $ownedByCurrentSession,
        public string $currentUserId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $holder = $this->holder;

        return [
            'locked' => $holder !== null && !$this->ownedByCurrentSession,
            'ownedByMe' => $this->ownedByCurrentSession,
            'holder' => $holder === null ? null : [
                'label' => $holder->label,
                'isSelf' => $holder->userId === $this->currentUserId,
                'since' => (new DateTimeImmutable('@'.$holder->acquiredAt))->format(DateTimeInterface::ATOM),
                'lastSeenAt' => (new DateTimeImmutable('@'.$holder->heartbeatAt))->format(DateTimeInterface::ATOM),
            ],
            'ttlSeconds' => PageEditLockService::TTL_SECONDS,
            'heartbeatSeconds' => PageEditLockService::HEARTBEAT_SECONDS,
        ];
    }
}
