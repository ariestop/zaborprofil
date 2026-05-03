<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

use App\Module\Content\Domain\ValueObject\PageVisibility;

final readonly class CreatePageCommand
{
    public function __construct(
        public string $type,
        public string $title,
        public string $slug,
        public string $path,
        public string $h1,
        public string $template = 'default',
        public int $sortOrder = 0,
        public bool $isIndexable = true,
        public ?string $parentId = null,
        public PageVisibility $visibility = PageVisibility::Public,
    ) {
    }
}
