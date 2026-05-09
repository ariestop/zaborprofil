<?php

declare(strict_types=1);

namespace App\Module\Content\Application\DTO;

use App\Module\Content\Domain\Entity\PageBlock;

final readonly class BuilderBlockOutput
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     * @param array<string, string> $metadata
     */
    public function __construct(
        public string $id,
        public string $type,
        public bool $enabled,
        public int $position,
        public array $content,
        public array $settings,
        public array $metadata,
    ) {
    }

    public static function fromBlock(PageBlock $block): self
    {
        return new self(
            (string) $block->id(),
            $block->type()->value,
            $block->isEnabled(),
            $block->position(),
            $block->content(),
            $block->settings(),
            [
                'createdAt' => $block->createdAt()->format(DATE_ATOM),
                'updatedAt' => $block->updatedAt()->format(DATE_ATOM),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'enabled' => $this->enabled,
            'position' => $this->position,
            'content' => $this->content,
            'settings' => $this->settings,
            'metadata' => $this->metadata,
        ];
    }
}
