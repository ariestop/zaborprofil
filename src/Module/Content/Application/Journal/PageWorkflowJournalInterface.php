<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Journal;

interface PageWorkflowJournalInterface
{
    /**
     * Актор определяется по текущей сессии; без аутентифицированного администратора запись считается системной.
     */
    public function record(PageWorkflowEvent $event): void;

    /**
     * @return list<PageWorkflowHistoryEntry> новые записи первыми
     */
    public function history(string $pageId, int $limit = 50): array;
}
