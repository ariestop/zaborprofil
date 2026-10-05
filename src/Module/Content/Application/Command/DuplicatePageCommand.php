<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class DuplicatePageCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $slug = null,
        public ?string $path = null,
    ) {
    }
}
