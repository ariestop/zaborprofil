<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Service\PageStatusTransitionPolicy;

/**
 * Состояние жизненного цикла страницы для админки: допустимые переходы с учётом прав текущего пользователя,
 * расписание, наличие неопубликованных правок и история событий.
 */
final readonly class PageWorkflowState
{
    private const int HISTORY_LIMIT = 50;

    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PageStatusTransitionPolicy $transitions,
        private PageWorkflowGuard $guard,
        private PageRevisionComparison $comparison,
        private PageWorkflowJournalInterface $journal,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(string $pageId): array
    {
        $page = $this->pages->get($this->contentId->fromString($pageId));
        $publication = $this->publications->findByPage((string) $page->id());

        return [
            'pageId' => (string) $page->id(),
            'status' => $page->status()->value,
            'scheduledPublishAt' => $page->scheduledPublishAt()?->format(DATE_ATOM),
            'scheduledUnpublishAt' => $page->scheduledUnpublishAt()?->format(DATE_ATOM),
            'hasUnpublishedChanges' => $this->comparison->hasUnpublishedChanges($page),
            'publishedRevision' => $this->revision($publication?->publishedRevision()),
            'scheduledRevision' => $this->revision($publication?->scheduledRevision()),
            'transitions' => $this->transitionsFor($page),
            'canCancelSchedule' => $this->canCancelSchedule($page),
            'history' => array_map(
                static fn ($entry): array => $entry->toArray(),
                $this->journal->history((string) $page->id(), self::HISTORY_LIMIT),
            ),
        ];
    }

    /**
     * @return list<array{status: string, allowed: bool}>
     */
    private function transitionsFor(Page $page): array
    {
        return array_map(
            fn (string $status): array => [
                'status' => $status,
                'allowed' => $this->guard->canTransition($page->status(), PageStatus::from($status)),
            ],
            $this->transitions->nextStatusValues($page->status()),
        );
    }

    private function canCancelSchedule(Page $page): bool
    {
        return match ($page->status()) {
            PageStatus::Scheduled => $this->guard->canTransition(PageStatus::Scheduled, PageStatus::Approved),
            PageStatus::Published => $page->scheduledUnpublishAt() !== null && $this->guard->canTransition(PageStatus::Published, PageStatus::Scheduled),
            default => false,
        };
    }

    /**
     * @return array{id: string, version: int, createdAt: string}|null
     */
    private function revision(?PageRevision $revision): ?array
    {
        return $revision === null ? null : [
            'id' => (string) $revision->id(),
            'version' => $revision->version(),
            'createdAt' => $revision->createdAt()->format(DATE_ATOM),
        ];
    }
}
