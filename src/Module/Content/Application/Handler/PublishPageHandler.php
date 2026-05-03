<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;

final readonly class PublishPageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    public function __invoke(PublishPageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $page->publish();
        $this->pages->save($page);

        $this->publicPageCache->invalidate($page->path());

        return PageOutput::fromPage($page);
    }
}
