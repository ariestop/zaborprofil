<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\CancelPageScheduleCommand;
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
 * Отменяет запланированную публикацию (scheduled -> approved) или запланированное снятие опубликованной страницы.
 */
final readonly class CancelPageScheduleHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublisher $publisher,
        private ContentId $contentId,
        private PageWorkflowGuard $guard,
        private CurrentAdminActor $actor,
        private PageWorkflowJournalInterface $journal,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(CancelPageScheduleCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(CancelPageScheduleCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $from = $page->status();
        $this->guard->assertAllowed($from, $from === PageStatus::Scheduled ? PageStatus::Approved : PageStatus::Scheduled);

        $details = array_filter([
            'publishAt' => $page->scheduledPublishAt()?->format(DATE_ATOM),
            'unpublishAt' => $page->scheduledUnpublishAt()?->format(DATE_ATOM),
        ]);

        $page->cancelSchedule($this->actor->id());
        $this->pages->save($page);

        $this->publisher->discardScheduledRevision($page);

        $this->journal->record(new PageWorkflowEvent(
            PageWorkflowEvent::SCHEDULE_CANCELLED,
            (string) $page->id(),
            $page->path(),
            $from->value,
            $page->status()->value,
            $command->comment,
            $details,
        ));

        return PageOutput::fromPage($page);
    }
}
