<?php

declare(strict_types=1);

namespace App\Module\AuditLog\UI\Admin;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/audit')]
final readonly class AuditLogApiController
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLog,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_audit_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return new JsonResponse([
                'error' => 'Access denied.',
                'code' => 'ACCESS_DENIED',
            ], 403);
        }

        $limit = $request->query->getInt('limit', 50);

        return new JsonResponse(array_map(
            self::entryToArray(...),
            $this->auditLog->findLatest($limit),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function entryToArray(AuditLogEntry $entry): array
    {
        return [
            'id' => (string) $entry->id(),
            'occurredAt' => $entry->occurredAt()->format(DATE_ATOM),
            'actorId' => $entry->actorId() === null ? null : (string) $entry->actorId(),
            'actorEmail' => $entry->actorEmail(),
            'ip' => $entry->ip(),
            'userAgent' => $entry->userAgent(),
            'requestId' => $entry->requestId(),
            'action' => $entry->action(),
            'entityType' => $entry->entityType(),
            'entityId' => $entry->entityId(),
            'oldValues' => $entry->oldValues(),
            'newValues' => $entry->newValues(),
        ];
    }
}
