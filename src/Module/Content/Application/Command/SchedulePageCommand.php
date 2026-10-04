<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class SchedulePageCommand
{
    public function __construct(
        public string $id,
        public ?string $publishAt,
        public ?string $unpublishAt = null,
        public ?string $comment = null,
    ) {
    }
}
