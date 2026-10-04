<?php

declare(strict_types=1);

namespace App\Module\Lead\Domain\Repository;

use App\Module\Lead\Domain\ValueObject\LeadAssignee;

interface LeadAssigneeDirectoryInterface
{
    /**
     * Активный пользователь, которому можно назначить заявку.
     */
    public function findAssignable(string $id): ?LeadAssignee;

    /**
     * @param list<string> $ids
     *
     * @return array<string, LeadAssignee> по идентификатору пользователя, включая неактивных
     */
    public function findMany(array $ids): array;

    /**
     * @return list<LeadAssignee>
     */
    public function assignable(): array;
}
