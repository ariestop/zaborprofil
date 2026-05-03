<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Application\Audit\PrePublishChecklist;
use App\Shared\Application\Logging\BusinessEventLogger;

final readonly class PublishPageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
        private BusinessEventLogger $businessEvents,
        private PrePublishChecklist $prePublishChecklist,
    ) {
    }

    public function __invoke(PublishPageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $this->prePublishChecklist->assertPublishable($page);

        $page->publish();
        $this->pages->save($page);

        $this->publicPageCache->invalidate($page->path());
        $this->businessEvents->log('page.published', [
            'page_id' => (string) $page->id(),
            'path' => $page->path(),
        ]);

        return PageOutput::fromPage($page);
    }
}
