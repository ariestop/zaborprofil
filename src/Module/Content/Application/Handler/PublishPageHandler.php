<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PageRevisionSnapshotBuilder;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Module\Seo\Application\Audit\PrePublishChecklist;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\Application\Logging\BusinessEventLogger;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class PublishPageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private PageRevisionRepositoryInterface $revisions,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PublicPageCacheInvalidator $publicPageCache,
        private BusinessEventLogger $businessEvents,
        private PrePublishChecklist $prePublishChecklist,
        private PageRevisionSnapshotBuilder $snapshotBuilder,
        private Security $security,
    ) {
    }

    public function __invoke(PublishPageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $this->prePublishChecklist->assertPublishable($page);
        $actorId = $this->actorId();
        $revision = $this->snapshotBuilder->build(
            $page,
            $this->revisions->nextVersionForPage((string) $page->id()),
            $this->blocks->findByPage((string) $page->id()),
            $actorId,
            $command->comment,
            ['action' => 'publish'],
        );
        $this->revisions->save($revision);

        $page->publish($actorId);
        $this->pages->save($page);

        $publication = $this->publications->getOrCreate($page);
        $publication->publish($revision);
        $this->publications->save($publication);

        $this->publicPageCache->invalidate($page->path());
        $this->businessEvents->log('page.published', [
            'page_id' => (string) $page->id(),
            'path' => $page->path(),
        ]);

        return PageOutput::fromPage($page);
    }

    private function actorId(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }
}
