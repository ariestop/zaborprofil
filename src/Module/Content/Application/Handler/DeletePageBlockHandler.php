<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\DeletePageBlockCommand;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;

final readonly class DeletePageBlockHandler
{
    public function __construct(
        private PageBlockRepositoryInterface $blocks,
        private ContentId $contentId,
    ) {
    }

    public function __invoke(DeletePageBlockCommand $command): void
    {
        $block = $this->blocks->get($this->contentId->fromString($command->id));
        $this->blocks->remove($block);
    }
}
