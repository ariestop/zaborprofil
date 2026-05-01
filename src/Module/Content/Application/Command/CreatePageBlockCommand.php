<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class CreatePageBlockCommand
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $pageId,
        public string $type,
        public string $name,
        public int $position,
        public array $content = [],
        public array $settings = [],
        public bool $isEnabled = true,
    ) {
    }
}
