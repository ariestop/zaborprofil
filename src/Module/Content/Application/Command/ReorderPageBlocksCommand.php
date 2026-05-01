<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class ReorderPageBlocksCommand
{
    /**
     * @param list<string> $blockIds
     */
    public function __construct(
        public string $pageId,
        public array $blockIds,
    ) {
    }
}
