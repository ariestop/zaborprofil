<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\PageBlock;

final readonly class PageBlockView
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $settings
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public int $position,
        public array $content,
        public array $settings,
    ) {
    }

    public static function fromBlock(PageBlock $block): self
    {
        return new self(
            (string) $block->id(),
            $block->type()->value,
            $block->name(),
            $block->position(),
            $block->content(),
            $block->settings(),
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        $normalizer = new SnapshotValueNormalizer();

        return new self(
            $normalizer->string($snapshot['id'] ?? null),
            $normalizer->string($snapshot['type'] ?? null, 'default'),
            $normalizer->string($snapshot['name'] ?? null, 'Block'),
            $normalizer->int($snapshot['position'] ?? null),
            $normalizer->stringKeyedArray($snapshot['content'] ?? null),
            $normalizer->stringKeyedArray($snapshot['settings'] ?? null),
        );
    }
}
