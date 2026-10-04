<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class BulkUpdatePagesCommand
{
    public const string ACTION_STATUS = 'status';
    public const string ACTION_INDEXABLE = 'indexable';

    /**
     * @param list<string> $ids
     */
    public function __construct(
        public array $ids,
        public string $action,
        public ?string $status = null,
        public ?bool $indexable = null,
        public ?string $comment = null,
    ) {
    }
}
