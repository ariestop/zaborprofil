<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CreatePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use InvalidArgumentException;

final readonly class CreatePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
    ) {
    }

    public function __invoke(CreatePageCommand $command): PageOutput
    {
        if ($this->pages->existsByPath($command->path)) {
            throw new InvalidArgumentException('Page path must be unique.');
        }

        $parent = $command->parentId === null ? null : $this->pages->get($this->contentId->fromString($command->parentId));
        $page = new Page(
            PageType::from($command->type),
            $command->title,
            $command->slug,
            $command->path,
            $command->h1,
            $command->template,
            $command->sortOrder,
            $command->isIndexable,
            $parent,
            $command->visibility,
        );

        $this->pages->save($page);

        return PageOutput::fromPage($page);
    }
}
