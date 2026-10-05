<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use App\Module\Seo\Application\Audit\PrePublishChecklist;
use App\Shared\Application\Logging\BusinessEventLogger;

/**
 * Публикация и снятие с публикации: общий код для ручных действий и планировщика.
 * Не проверяет права и переходы статусов — это задача вызывающего (handler/планировщик).
 */
final readonly class PagePublisher
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PageBlockRepositoryInterface $blocks,
        private PageRevisionRepositoryInterface $revisions,
        private PagePublicationRepositoryInterface $publications,
        private PublicPageCacheInvalidator $publicPageCache,
        private BusinessEventLogger $businessEvents,
        private PrePublishChecklist $prePublishChecklist,
        private PageRevisionSnapshotBuilder $snapshotBuilder,
    ) {
    }

    public function publish(Page $page, ?string $actorId, ?string $comment = null, string $action = 'publish'): PageRevision
    {
        $this->prePublishChecklist->assertPublishable($page);
        $revision = $this->snapshot($page, $actorId, $comment, $action);

        $page->publish($actorId);
        $this->pages->save($page);

        $publication = $this->publications->getOrCreate($page);
        $publication->publish($revision);
        $this->publications->save($publication);

        $this->publicPageCache->invalidate($page->path());
        $this->businessEvents->log('page.published', [
            'page_id' => (string) $page->id(),
            'path' => $page->path(),
            'action' => $action,
        ]);

        return $revision;
    }

    public function unpublish(Page $page, ?string $actorId, string $action = 'unpublish'): void
    {
        $page->unpublish($actorId);
        $this->pages->save($page);
        $this->withdraw($page);
        $this->businessEvents->log('page.unpublished', [
            'page_id' => (string) $page->id(),
            'path' => $page->path(),
            'action' => $action,
        ]);
    }

    /**
     * Снимает публикацию в {@see \App\Module\Content\Domain\Entity\PagePublication} и сбрасывает публичный кэш.
     * Страница к этому моменту уже переведена в нужный статус и сохранена.
     */
    public function withdraw(Page $page): void
    {
        $publication = $this->publications->findByPage((string) $page->id());
        if ($publication !== null) {
            $publication->unpublish();
            $this->publications->save($publication);
        }

        $this->publicPageCache->invalidate($page->path());
    }

    public function discardScheduledRevision(Page $page): void
    {
        $publication = $this->publications->findByPage((string) $page->id());
        if ($publication !== null) {
            $publication->cancelSchedule();
            $this->publications->save($publication);
        }
    }

    public function snapshot(Page $page, ?string $actorId, ?string $comment, string $action): PageRevision
    {
        $revision = $this->snapshotBuilder->build(
            $page,
            $this->revisions->nextVersionForPage((string) $page->id()),
            $this->blocks->findByPage((string) $page->id()),
            $actorId,
            $comment,
            ['action' => $action],
        );
        $this->revisions->save($revision);

        return $revision;
    }
}
