<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\UpdatePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use InvalidArgumentException;

final readonly class UpdatePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
    ) {
    }

    public function __invoke(UpdatePageCommand $command): PageOutput
    {
        $pageId = $this->contentId->fromString($command->id);

        if ($this->pages->existsByPath($command->path, $pageId)) {
            throw new InvalidArgumentException('Page path must be unique.');
        }

        $page = $this->pages->get($pageId);
        $parent = $command->parentId === null ? null : $this->pages->get($this->contentId->fromString($command->parentId));

        $page->update(
            PageType::from($command->type),
            $command->title,
            $command->slug,
            $command->path,
            $command->h1,
            $command->template,
            $command->sortOrder,
            $command->isIndexable,
            $parent,
        );

        $this->pages->save($page);

        return PageOutput::fromPage($page);
    }
}
