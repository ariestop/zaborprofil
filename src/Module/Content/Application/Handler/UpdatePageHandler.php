<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\UpdatePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use InvalidArgumentException;

final readonly class UpdatePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
        private BusinessEventLogger $businessEvents,
    ) {
    }

    public function __invoke(UpdatePageCommand $command): PageOutput
    {
        $pageId = $this->contentId->fromString($command->id);

        if ($this->pages->existsByPath($command->path, $pageId)) {
            throw new InvalidArgumentException('Page path must be unique.');
        }

        $page = $this->pages->get($pageId);
        $previousPath = $page->path();
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

        $this->publicPageCache->invalidateMany([$previousPath, $page->path()]);
        if ($previousPath !== $page->path()) {
            $this->businessEvents->log('page.pathChanged', [
                'page_id' => (string) $page->id(),
                'old_path' => $previousPath,
                'new_path' => $page->path(),
            ]);
        }

        return PageOutput::fromPage($page);
    }
}
