<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\Page;

final readonly class PageBuilderDocumentOutput
{
    /**
     * @param list<BuilderBlockOutput> $blocks
     */
    public function __construct(
        public string $pageId,
        public ?string $updatedAt,
        public string $version,
        public array $blocks,
    ) {
    }

    /**
     * @param list<BuilderBlockOutput> $blocks
     */
    public static function fromPage(Page $page, array $blocks, string $version): self
    {
        return new self(
            (string) $page->id(),
            $page->updatedAt()->format(DATE_ATOM),
            $version,
            $blocks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pageId' => $this->pageId,
            'updatedAt' => $this->updatedAt,
            'version' => $this->version,
            'blocks' => array_map(static fn (BuilderBlockOutput $block): array => $block->toArray(), $this->blocks),
        ];
    }
}
