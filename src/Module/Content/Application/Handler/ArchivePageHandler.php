<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\ArchivePageCommand;
use App\Module\Content\Application\DTO\PageOutput;
use App\Module\Content\Application\Journal\PageWorkflowEvent;
use App\Module\Content\Application\Journal\PageWorkflowJournalInterface;
use App\Module\Content\Application\Service\ContentId;
use App\Module\Content\Application\Service\PagePublisher;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Shared\Application\Transaction\TransactionRunnerInterface;

final readonly class ArchivePageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PagePublisher $publisher,
        private PageWorkflowJournalInterface $journal,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(ArchivePageCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(ArchivePageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $from = $page->status();
        $page->archive();
        $this->pages->save($page);
        $this->publisher->withdraw($page);

        $this->journal->record(new PageWorkflowEvent(
            PageWorkflowEvent::ARCHIVED,
            (string) $page->id(),
            $page->path(),
            $from->value,
            $page->status()->value,
        ));

        return PageOutput::fromPage($page);
    }
}
