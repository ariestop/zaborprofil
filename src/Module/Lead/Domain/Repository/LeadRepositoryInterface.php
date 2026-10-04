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

    public function search(LeadSearchCriteria $criteria): LeadSearchResult;

    /**
     * Заявки под фильтром без пагинации, но не более $limit.
     *
     * @return list<Lead>
     */
    public function findForExport(LeadSearchCriteria $criteria, int $limit): array;

    /**
     * Количество заявок по статусам с учётом всех фильтров, кроме статуса.
     * Отсутствующие статусы возвращаются с нулём.
     *
     * @return array<string, int>
     */
    public function countByStatus(LeadSearchCriteria $criteria): array;

    /**
     * @return list<string>
     */
    public function distinctSources(): array;
}
