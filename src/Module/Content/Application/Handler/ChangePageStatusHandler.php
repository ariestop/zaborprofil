<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ChangePageStatusCommand;
use App\Module\Content\Application\Command\PublishPageCommand;
use App\Module\Content\Application\Command\SchedulePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Journal\PageWorkflowEvent;
use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\CurrentAdminActor;
use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Application\Service\PageWorkflowGuard;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Transaction\TransactionRunnerInterface;

/**
 * Универсальная смена статуса: draft -> review -> approved -> published/scheduled и обратные переходы.
 * Публикация и планирование делегируются профильным handler-ам, остальные переходы выполняются здесь.
 */
final readonly class ChangePageStatusHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PageWorkflowGuard $guard,
        private CurrentAdminActor $actor,
        private PagePublisher $publisher,
        private PublishPageHandler $publish,
        private SchedulePageHandler $schedule,
        private PageWorkflowJournalInterface $journal,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(ChangePageStatusCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(ChangePageStatusCommand $command): PageOutput
    {
        $next = PageStatus::from($command->status);

        if ($next === PageStatus::Published) {
            return ($this->publish)(new PublishPageCommand($command->id, $command->comment));
        }

        if ($next === PageStatus::Scheduled) {
            return ($this->schedule)(new SchedulePageCommand($command->id, $command->publishAt, $command->unpublishAt, $command->comment));
        }

        $page = $this->pages->get($this->contentId->fromString($command->id));
        $from = $page->status();
        $this->guard->assertAllowed($from, $next);

        if ($from === $next) {
            return PageOutput::fromPage($page);
        }

        $actorId = $this->actor->id();

        match ($next) {
            PageStatus::Review => $page->submitForReview($actorId),
            PageStatus::Approved => $page->approve($actorId),
            PageStatus::Unpublished => $page->unpublish($actorId),
            PageStatus::Archived => $page->archive($actorId),
            PageStatus::Deleted => $page->delete(),
            default => $page->restoreToDraft($actorId),
        };

        $this->pages->save($page);
        if ($from === PageStatus::Scheduled) {
            $this->publisher->discardScheduledRevision($page);
        }

        if (\in_array($next, [PageStatus::Unpublished, PageStatus::Archived, PageStatus::Deleted], true)) {
            $this->publisher->withdraw($page);
        }

        $this->journal->record(new PageWorkflowEvent(
            match ($next) {
                PageStatus::Unpublished => PageWorkflowEvent::UNPUBLISHED,
                PageStatus::Archived => PageWorkflowEvent::ARCHIVED,
                default => PageWorkflowEvent::STATUS_CHANGED,
            },
            (string) $page->id(),
            $page->path(),
            $from->value,
            $next->value,
            $command->comment,
        ));

        return PageOutput::fromPage($page);
    }
}
