<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ReorderPageBlocksCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use InvalidArgumentException;

final readonly class ReorderPageBlocksHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    /**
     * @return list<PageBlockOutput>
     */
    public function __invoke(ReorderPageBlocksCommand $command): array
    {
        $pageId = $this->contentId->fromString($command->pageId);
        $page = $this->pages->get($pageId);
        $existingBlocks = $this->blocks->findByPage($pageId);
        $existingBlockIds = array_map(static fn (PageBlock $block): string => (string) $block->id(), $existingBlocks);

        if (\count($command->blockIds) !== \count(array_unique($command->blockIds))) {
            throw new InvalidArgumentException('Block order cannot contain duplicate blocks.');
        }

        $submittedBlockIds = $command->blockIds;
        sort($existingBlockIds);
        sort($submittedBlockIds);

        if ($submittedBlockIds !== $existingBlockIds) {
            throw new InvalidArgumentException('Block order must include every block on the page exactly once.');
        }

        /** @var array<string, PageBlock> $blocksById */
        $blocksById = [];
        foreach ($existingBlocks as $block) {
            $blocksById[(string) $block->id()] = $block;
        }

        $reordered = [];
        foreach ($command->blockIds as $position => $blockId) {
            $block = $blocksById[$blockId] ?? throw new InvalidArgumentException('Block does not belong to the page.');

            $block->moveTo($position);
            $reordered[] = $block;
        }

        $this->blocks->saveAll($reordered);

        $this->publicPageCache->invalidate($page->path());

        return array_map(PageBlockOutput::fromBlock(...), $reordered);
    }
}
