<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Repository;

use App\Module\Lead\Domain\Entity\Lead;

interface LeadRepositoryInterface
{
    public function save(Lead $lead): void;

    public function get(string $id): Lead;

    /**
     * @return list<Lead>
     */
    public function findLatest(int $limit = 100): array;
}
