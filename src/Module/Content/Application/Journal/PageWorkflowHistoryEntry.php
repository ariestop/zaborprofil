<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Journal;

final readonly class PageWorkflowHistoryEntry
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public string $id,
        public string $event,
        public string $occurredAt,
        public string $actor,
        public ?string $fromStatus,
        public ?string $toStatus,
        public ?string $comment,
        public array $details,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'occurredAt' => $this->occurredAt,
            'actor' => $this->actor,
            'fromStatus' => $this->fromStatus,
            'toStatus' => $this->toStatus,
            'comment' => $this->comment,
            'details' => $this->details,
        ];
    }
}
