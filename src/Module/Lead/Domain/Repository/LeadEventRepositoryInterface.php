<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Repository;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Entity\LeadEvent;

interface LeadEventRepositoryInterface
{
    public function save(LeadEvent $event): void;

    /**
     * Хронология заявки: от новых событий к старым.
     *
     * @return list<LeadEvent>
     */
    public function findByLead(Lead $lead): array;
}
