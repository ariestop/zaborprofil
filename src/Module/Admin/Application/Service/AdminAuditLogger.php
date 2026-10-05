<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\Infrastructure\Http\RequestIdSubscriber;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AdminAuditLogger
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     */
    public function log(string $action, string $entityType, ?string $entityId, array $oldValues = [], array $newValues = []): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();
        $actorId = $user instanceof AdminUser ? $user->id() : null;
        $actorEmail = $user instanceof AdminUser ? $user->email() : null;

        $this->auditLogRepository->save(new AuditLogEntry(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            oldValues: $oldValues,
            newValues: $newValues,
            actorId: $actorId,
            actorEmail: $actorEmail,
            ip: $request?->getClientIp(),
            userAgent: $request?->headers->get('User-Agent'),
            requestId: $request?->attributes->getString(RequestIdSubscriber::ATTRIBUTE) ?: null,
        ));
    }
}
