<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\SchedulePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Journal\PageWorkflowEvent;
use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\CurrentAdminActor;
use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Application\Service\PageWorkflowGuard;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Seo\Application\Audit\PrePublishChecklist;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Clock\ClockInterface;

/**
 * Планирует публикацию одобренной страницы либо снятие уже опубликованной.
 */
final readonly class SchedulePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublicationRepositoryInterface $publications,
        private ContentId $contentId,
        private PageWorkflowGuard $guard,
        private CurrentAdminActor $actor,
        private PagePublisher $publisher,
        private PrePublishChecklist $prePublishChecklist,
        private PageWorkflowJournalInterface $journal,
        private ClockInterface $clock,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(SchedulePageCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(SchedulePageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $from = $page->status();
        $this->guard->assertAllowed($from, PageStatus::Scheduled);

        $now = $this->clock->now();
        $publishAt = $this->parse($command->publishAt, 'publishAt');
        $unpublishAt = $this->parse($command->unpublishAt, 'unpublishAt');
        $actorId = $this->actor->id();

        if ($from === PageStatus::Published) {
            if ($publishAt !== null) {
                throw new InvalidArgumentException('A published page can only be scheduled for unpublishing.');
            }

            if ($unpublishAt === null || $unpublishAt <= $now) {
                throw new InvalidArgumentException('Scheduled unpublish date must be in the future.');
            }

            $page->scheduleUnpublish($unpublishAt, $actorId);
            $this->pages->save($page);
        } else {
            if ($publishAt === null || $publishAt <= $now) {
                throw new InvalidArgumentException('Scheduled publish date must be in the future.');
            }

            if ($unpublishAt !== null && $unpublishAt <= $now) {
                throw new InvalidArgumentException('Scheduled unpublish date must be in the future.');
            }

            $this->prePublishChecklist->assertPublishable($page);
            $page->schedule($publishAt, $unpublishAt, $actorId);
            $this->pages->save($page);

            $publication = $this->publications->getOrCreate($page);
            $publication->schedule($this->publisher->snapshot($page, $actorId, $command->comment, 'schedule'));
            $this->publications->save($publication);
        }

        $this->journal->record(new PageWorkflowEvent(
            PageWorkflowEvent::SCHEDULED,
            (string) $page->id(),
            $page->path(),
            $from->value,
            $page->status()->value,
            $command->comment,
            array_filter([
                'publishAt' => $page->scheduledPublishAt()?->format(DATE_ATOM),
                'unpublishAt' => $page->scheduledUnpublishAt()?->format(DATE_ATOM),
            ]),
        ));

        return PageOutput::fromPage($page);
    }

    private function parse(?string $value, string $field): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable(trim($value));
        } catch (Exception) {
            throw new InvalidArgumentException(\sprintf('Field "%s" must be an ISO 8601 date-time.', $field));
        }

        return $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
}
