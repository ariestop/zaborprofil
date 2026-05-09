<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Admin\Application\Service\DangerousActionConfirmationService;
use App\Module\Admin\Application\Service\SecurityAuditService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/security')]
final readonly class SystemSecurityController
{
    use SystemControllerTrait;

    public function __construct(
        private SecurityAuditService $securityAudit,
        private DangerousActionConfirmationService $confirmation,
        private AdminAuditLogger $auditLogger,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
    ) {
    }

    #[Route('', name: 'admin_api_system_security', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->securityAudit->status());
    }

    #[Route('/confirm-token', name: 'admin_api_system_security_confirm_token', methods: ['POST'])]
    public function issueConfirmToken(Request $request): JsonResponse
    {
        if (
            !$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)
            || !$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_DANGEROUS)
        ) {
            return $this->accessDenied();
        }

        $payload = $this->jsonPayload($request);
        $action = isset($payload['action']) && \is_string($payload['action']) ? trim($payload['action']) : '';
        if ($action === '') {
            return $this->validationError('Field "action" is required.');
        }

        $actorId = $this->actorId();
        if ($actorId === null) {
            return $this->accessDenied();
        }

        $token = $this->confirmation->issue($actorId, $action);
        $this->auditLogger->log(
            action: 'system.confirm_token.issued',
            entityType: 'system.security',
            entityId: $action,
            newValues: ['expiresAt' => $token['expiresAt']],
        );

        return new JsonResponse($token, 201);
    }

    private function actorId(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }
}
