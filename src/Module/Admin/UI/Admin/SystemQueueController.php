<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Admin\Application\Service\DangerousActionConfirmationService;
use App\Module\Admin\Application\Service\QueueMonitorService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/queues')]
final readonly class SystemQueueController
{
    use SystemControllerTrait;

    public function __construct(
        private QueueMonitorService $queues,
        private DangerousActionConfirmationService $confirmation,
        private AdminAuditLogger $auditLogger,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
    ) {
    }

    #[Route('', name: 'admin_api_system_queues', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->queues->status());
    }

    #[Route('/retry-failed', name: 'admin_api_system_queues_retry_failed', methods: ['POST'])]
    public function retryFailed(Request $request): JsonResponse
    {
        return $this->runDangerousAction($request, 'queue.retry.failed', fn (): array => $this->queues->retryFailed());
    }

    #[Route('/remove-failed', name: 'admin_api_system_queues_remove_failed', methods: ['POST'])]
    public function removeFailed(Request $request): JsonResponse
    {
        return $this->runDangerousAction($request, 'queue.remove.failed', fn (): array => $this->queues->removeFailed());
    }

    /**
     * @param \Closure(): array<string, mixed> $handler
     */
    private function runDangerousAction(Request $request, string $action, \Closure $handler): JsonResponse
    {
        if (
            !$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)
            || !$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_DANGEROUS)
        ) {
            return $this->accessDenied();
        }

        $payload = $this->jsonPayload($request);
        $confirmToken = isset($payload['confirmToken']) && \is_string($payload['confirmToken']) ? $payload['confirmToken'] : '';
        $actorId = $this->actorId();
        if ($actorId === null) {
            return $this->accessDenied();
        }

        $this->auditLogger->log(
            action: 'system.queue.attempt',
            entityType: 'system.queue',
            entityId: $action,
        );

        try {
            $this->confirmation->consume($actorId, $action, $confirmToken);
            $result = $handler();
            $this->auditLogger->log(
                action: 'system.queue.success',
                entityType: 'system.queue',
                entityId: $action,
                newValues: ['result' => $result],
            );

            return new JsonResponse($result, ($result['exitCode'] ?? 1) === 0 ? 200 : 500);
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->log(
                action: 'system.queue.failure',
                entityType: 'system.queue',
                entityId: $action,
                newValues: ['error' => $exception->getMessage()],
            );

            return $this->validationError($exception->getMessage());
        }
    }

    private function actorId(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }
}
