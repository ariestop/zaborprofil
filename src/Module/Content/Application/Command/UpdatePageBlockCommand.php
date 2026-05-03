<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

use App\Module\Content\Domain\ValueObject\PageVisibility;

final readonly class UpdatePageBlockCommand
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public array $content = [],
        public array $settings = [],
        public bool $isEnabled = true,
        public PageVisibility $visibility = PageVisibility::Public,
    ) {
    }
}
