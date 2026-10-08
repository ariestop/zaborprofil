<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Application\Journal\PageWorkflowEvent;
use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Exception\ClientErrorClassifier;
use App\Shared\Application\Transaction\TransactionRunnerInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Выполняет отложенные публикации и снятия, у которых наступило время.
 *
 * Идемпотентность: страница выбирается по статусу (`scheduled` / `published`) и дате; после обработки статус
 * меняется, поэтому повторный запуск ничего не делает. Параллельные запуски исключает блокировка в команде.
 * Страница, не прошедшая чек-лист публикации, возвращается в `approved`, а причина пишется в журнал —
 * без бесконечных повторов и спама.
 */
final readonly class ScheduledPagePublisher
{
    public const string SCHEDULED_COMMENT = 'Scheduled publication';

    public function __construct(
        private PageRepositoryInterface $pages,
        private PagePublisher $publisher,
        private PageWorkflowJournalInterface $journal,
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.business')]
        private LoggerInterface $logger,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function run(int $limit = 100, bool $dryRun = false): ScheduledPublishingReport
    {
        $now = $this->clock->now();
        $report = new ScheduledPublishingReport();

        foreach ($this->pages->findDueForScheduledPublish($now, $limit) as $page) {
            $dryRun ? $this->previewPublish($page, $now, $report) : $this->publishDue($page, $now, $report);
        }

        foreach ($this->pages->findDueForScheduledUnpublish($now, $limit) as $page) {
            $dryRun ? $report->add((string) $page->id(), $page->path(), ScheduledPublishingReport::OUTCOME_WOULD_UNPUBLISH) : $this->unpublishDue($page, $report);
        }

        return $report;
    }

    private function previewPublish(Page $page, DateTimeImmutable $now, ScheduledPublishingReport $report): void
    {
        $expired = $this->isWindowMissed($page, $now);
        $report->add(
            (string) $page->id(),
            $page->path(),
            $expired ? ScheduledPublishingReport::OUTCOME_FAILED : ScheduledPublishingReport::OUTCOME_WOULD_PUBLISH,
            $expired ? 'Scheduled unpublish date has already passed.' : null,
        );
    }

    private function publishDue(Page $page, DateTimeImmutable $now, ScheduledPublishingReport $report): void
    {
        $pageId = (string) $page->id();
        $details = array_filter([
            'publishAt' => $page->scheduledPublishAt()?->format(DATE_ATOM),
            'unpublishAt' => $page->scheduledUnpublishAt()?->format(DATE_ATOM),
        ]);

        try {
            if ($this->isWindowMissed($page, $now)) {
                throw new InvalidArgumentException('Scheduled unpublish date has already passed.');
            }

            $this->transactions->run(function () use ($page, $pageId, $details): void {
                $revision = $this->publisher->publish($page, null, self::SCHEDULED_COMMENT, 'scheduled_publish');
                $this->journal->record(new PageWorkflowEvent(
                    PageWorkflowEvent::SCHEDULED_PUBLISHED,
                    $pageId,
                    $page->path(),
                    'scheduled',
                    $page->status()->value,
                    self::SCHEDULED_COMMENT,
                    $details + ['revisionId' => (string) $revision->id(), 'version' => $revision->version()],
                ));
            });
            $report->add($pageId, $page->path(), ScheduledPublishingReport::OUTCOME_PUBLISHED);
        } catch (Throwable $exception) {
            // Отказ чек-листа или неверное расписание — отменяем публикацию. Исключения Doctrine с тем же
            // базовым типом — сбой программы: расписание сохраняется, ошибка уходит в лог.
            if ($exception instanceof InvalidArgumentException && ClientErrorClassifier::isValidationError($exception)) {
                $this->abandonSchedule($page, $exception->getMessage(), $details);
                $report->add($pageId, $page->path(), ScheduledPublishingReport::OUTCOME_FAILED, $exception->getMessage());

                return;
            }

            $this->logger->error('Scheduled publication crashed.', ['page_id' => $pageId, 'exception' => $exception]);
            $report->add($pageId, $page->path(), ScheduledPublishingReport::OUTCOME_ERROR, $exception->getMessage());
        }
    }

    private function unpublishDue(Page $page, ScheduledPublishingReport $report): void
    {
        $pageId = (string) $page->id();
        $unpublishAt = $page->scheduledUnpublishAt()?->format(DATE_ATOM);

        try {
            $this->transactions->run(function () use ($page, $pageId, $unpublishAt): void {
                $this->publisher->unpublish($page, null, 'scheduled_unpublish');
                $this->journal->record(new PageWorkflowEvent(
                    PageWorkflowEvent::SCHEDULED_UNPUBLISHED,
                    $pageId,
                    $page->path(),
                    'published',
                    $page->status()->value,
                    null,
                    array_filter(['unpublishAt' => $unpublishAt]),
                ));
            });
            $report->add($pageId, $page->path(), ScheduledPublishingReport::OUTCOME_UNPUBLISHED);
        } catch (Throwable $exception) {
            $this->logger->error('Scheduled unpublish crashed.', ['page_id' => $pageId, 'exception' => $exception]);
            $report->add($pageId, $page->path(), ScheduledPublishingReport::OUTCOME_ERROR, $exception->getMessage());
        }
    }

    /**
     * @param array<string, string> $details
     */
    private function abandonSchedule(Page $page, string $reason, array $details): void
    {
        $this->transactions->run(function () use ($page, $reason, $details): void {
            $page->cancelSchedule();
            $this->pages->save($page);
            $this->publisher->discardScheduledRevision($page);
            $this->journal->record(new PageWorkflowEvent(
                PageWorkflowEvent::SCHEDULE_FAILED,
                (string) $page->id(),
                $page->path(),
                'scheduled',
                $page->status()->value,
                $reason,
                $details,
            ));
        });
        $this->logger->warning('Scheduled publication was rejected.', [
            'event' => 'page.scheduled_publish_failed',
            'page_id' => (string) $page->id(),
            'reason' => $reason,
        ]);
    }

    private function isWindowMissed(Page $page, DateTimeImmutable $now): bool
    {
        $unpublishAt = $page->scheduledUnpublishAt();

        return $unpublishAt !== null && $unpublishAt <= $now;
    }
}
