<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\UpdatePageBlockCommand;
use App\Module\Content\Application\DTO\PageBlockOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Enum\BlockType;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;

final readonly class UpdatePageBlockHandler
{
    public function __construct(
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(UpdatePageBlockCommand $command): PageBlockOutput
    {
        $block = $this->blocks->get($this->contentId->fromString($command->id));
        $block->update(BlockType::from($command->type), $command->name, $command->content, $command->settings, $command->isEnabled);
        $this->blocks->save($block);

        $this->publicPageCache->invalidate($block->page()->path());

        return PageBlockOutput::fromBlock($block);
    }
}
