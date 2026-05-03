<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ArchivePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class ArchivePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(ArchivePageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $page->archive();
        $this->pages->save($page);
        $publication = $this->publications->findByPage((string) $page->id());
        if ($publication !== null) {
            $publication->unpublish();
            $this->publications->save($publication);
        }

        $this->publicPageCache->invalidate($page->path());

        return PageOutput::fromPage($page);
    }
}
