<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\PageBlock;

final readonly class PageBlockOutput
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $id,
        public string $pageId,
        public string $type,
        public string $name,
        public int $position,
        public bool $isEnabled,
        public array $content,
        public array $settings,
    ) {
    }

    public static function fromBlock(PageBlock $block): self
    {
        return new self(
            (string) $block->id(),
            (string) $block->page()->id(),
            $block->type()->value,
            $block->name(),
            $block->position(),
            $block->isEnabled(),
            $block->content(),
            $block->settings(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pageId' => $this->pageId,
            'type' => $this->type,
            'name' => $this->name,
            'position' => $this->position,
            'isEnabled' => $this->isEnabled,
            'content' => $this->content,
            'settings' => $this->settings,
        ];
    }
}
