<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ReorderPageBlocksCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use InvalidArgumentException;

final readonly class ReorderPageBlocksHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
    ) {
    }

    /**
     * @return list<PageBlockOutput>
     */
    public function __invoke(ReorderPageBlocksCommand $command): array
    {
        $pageId = $this->contentId->fromString($command->pageId);
        $this->pages->get($pageId);

        $outputs = [];
        foreach ($command->blockIds as $position => $blockId) {
            $block = $this->blocks->get($this->contentId->fromString($blockId));

            if (!$block->page()->id()->equals($pageId)) {
                throw new InvalidArgumentException('Block does not belong to the page.');
            }

            $block->moveTo($position);
            $this->blocks->save($block);
            $outputs[] = PageBlockOutput::fromBlock($block);
        }

        return $outputs;
    }
}
