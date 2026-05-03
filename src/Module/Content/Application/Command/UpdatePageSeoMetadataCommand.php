<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Command;

final readonly class UpdatePageSeoMetadataCommand
{
    /**
     * @param list<array<string, mixed>>|null $jsonLd
     */
    public function __construct(
        public string $id,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $ogType = null,
        public ?array $jsonLd = null,
    ) {
    }
}
