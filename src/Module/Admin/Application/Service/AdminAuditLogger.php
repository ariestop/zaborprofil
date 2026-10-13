<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\Module\Auth\Application\Security\CurrentAdminInterface;
use App\Shared\Application\Http\RequestContextInterface;
use Symfony\Component\Uid\Ulid;

final readonly class AdminAuditLogger
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository,
        private CurrentAdminInterface $currentAdmin,
        private RequestContextInterface $request,
    ) {
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function log(string $action, string $entityType, ?string $entityId, array $oldValues = [], array $newValues = []): void
    {
        $actorId = $this->currentAdmin->id();

        $this->auditLogRepository->save(new AuditLogEntry(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            oldValues: $oldValues,
            newValues: $newValues,
            actorId: $actorId === null ? null : Ulid::fromString($actorId),
            actorEmail: $actorId === null ? null : $this->currentAdmin->identifier(),
            ip: $this->request->clientIp(),
            userAgent: $this->request->userAgent(),
            requestId: $this->request->requestId(),
        ));
    }
}
