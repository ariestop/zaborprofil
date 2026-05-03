<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CreatePageBlockCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\Service\BlockSchemaRegistry;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class CreatePageBlockHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
        private BlockSchemaRegistry $blockSchemas,
    ) {
    }

    public function __invoke(CreatePageBlockCommand $command): PageBlockOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->pageId));
        $type = BlockType::from($command->type);
        $this->blockSchemas->validate($type, $command->content);
        $block = new PageBlock(
            $page,
            $type,
            $command->name,
            $command->position,
            $command->content,
            $command->settings,
            $command->isEnabled,
            $command->visibility,
        );

        $this->blocks->save($block);

        $this->publicPageCache->invalidate($page->path());

        return PageBlockOutput::fromBlock($block);
    }
}
