<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class RollbackPageRevisionCommand
{
    public function __construct(
        public string $pageId,
        public string $revisionId,
    ) {
    }
}
