<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CreatePageBlockCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
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
    ) {
    }

    public function __invoke(CreatePageBlockCommand $command): PageBlockOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->pageId));
        $block = new PageBlock(
            $page,
            BlockType::from($command->type),
            $command->name,
            $command->position,
            $command->content,
            $command->settings,
            $command->isEnabled,
        );

        $this->blocks->save($block);

        $this->publicPageCache->invalidate($page->path());

        return PageBlockOutput::fromBlock($block);
    }
}
