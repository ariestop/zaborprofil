<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Admin\Application\Service\DangerousActionConfirmationService;
use App\Module\Admin\Application\Service\ProcessManagerService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/processes')]
final readonly class SystemProcessesController
{
    use SystemControllerTrait;

    public function __construct(
        private ProcessManagerService $processManager,
        private DangerousActionConfirmationService $confirmation,
        private AdminAuditLogger $auditLogger,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
    ) {
    }

    #[Route('', name: 'admin_api_system_processes', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->processManager->status());
    }

    #[Route('/restart', name: 'admin_api_system_processes_restart', methods: ['POST'])]
    public function restart(Request $request): JsonResponse
    {
        return $this->runDangerousAction($request, 'process.restart', fn (string $service): array => $this->processManager->restart($service));
    }

    #[Route('/reload', name: 'admin_api_system_processes_reload', methods: ['POST'])]
    public function reload(Request $request): JsonResponse
    {
        return $this->runDangerousAction($request, 'process.reload', fn (string $service): array => $this->processManager->reload($service));
    }

    /**
     * @param \Closure(string): array<string, mixed> $handler
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
        $service = isset($payload['service']) && \is_string($payload['service']) ? trim($payload['service']) : '';
        $confirmToken = isset($payload['confirmToken']) && \is_string($payload['confirmToken']) ? $payload['confirmToken'] : '';

        if ($service === '') {
            return $this->validationError('Field "service" is required.');
        }

        $actorId = $this->actorId();
        if ($actorId === null) {
            return $this->accessDenied();
        }

        $this->auditLogger->log(
            action: 'system.process.attempt',
            entityType: 'system.process',
            entityId: $service,
            newValues: ['operation' => $action],
        );

        try {
            $this->confirmation->consume($actorId, $action.':'.$service, $confirmToken);
            $result = $handler($service);
            $this->auditLogger->log(
                action: 'system.process.success',
                entityType: 'system.process',
                entityId: $service,
                newValues: ['operation' => $action, 'result' => $result],
            );

            return new JsonResponse($result, ($result['exitCode'] ?? 1) === 0 ? 200 : 500);
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->log(
                action: 'system.process.failure',
                entityType: 'system.process',
                entityId: $service,
                newValues: ['operation' => $action, 'error' => $exception->getMessage()],
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
