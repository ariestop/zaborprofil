<?php

declare(strict_types=1);

namespace App\Module\AuditLog\Domain\Repository;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;

interface AuditLogRepositoryInterface
{
    public function save(AuditLogEntry $entry): void;

    /**
     * @return list<AuditLogEntry>
     */
    public function findLatest(int $limit = 50): array;

    /**
     * @return list<AuditLogEntry> новые записи первыми
     */
    public function findByEntity(string $entityType, string $entityId, string $actionPrefix, int $limit = 50): array;
}
