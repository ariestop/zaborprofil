<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Handler;

use App\Module\Content\Application\Command\PublishPageCommand;
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

final readonly class PublishPageHandler
{
    public function __construct(
        private PageRepositoryInterface $pages,
        private ContentId $contentId,
        private PageWorkflowGuard $guard,
        private CurrentAdminActor $actor,
        private PagePublisher $publisher,
        private PageWorkflowJournalInterface $journal,
        private TransactionRunnerInterface $transactions,
    ) {
    }

    public function __invoke(PublishPageCommand $command): PageOutput
    {
        // Все записи use case — одной транзакцией: при ошибке на любом шаге не остаётся половины изменений.
        return $this->transactions->run(fn (): PageOutput => $this->handle($command));
    }

    private function handle(PublishPageCommand $command): PageOutput
    {
        $page = $this->pages->get($this->contentId->fromString($command->id));
        $from = $page->status();
        $this->guard->assertAllowed($from, PageStatus::Published);

        $revision = $this->publisher->publish($page, $this->actor->id(), $command->comment);

        $this->journal->record(new PageWorkflowEvent(
            PageWorkflowEvent::PUBLISHED,
            (string) $page->id(),
            $page->path(),
            $from->value,
            $page->status()->value,
            $command->comment,
            ['revisionId' => (string) $revision->id(), 'version' => $revision->version()],
        ));

        return PageOutput::fromPage($page);
    }
}
