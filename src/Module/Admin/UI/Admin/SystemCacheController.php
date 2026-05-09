<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Admin\Application\Service\CacheManagerService;
use App\Module\Admin\Application\Service\DangerousActionConfirmationService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/cache')]
final readonly class SystemCacheController
{
    use SystemControllerTrait;

    public function __construct(
        private CacheManagerService $cacheManager,
        private DangerousActionConfirmationService $confirmation,
        private AdminAuditLogger $auditLogger,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
    ) {
    }

    #[Route('', name: 'admin_api_system_cache', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->cacheManager->status());
    }

    #[Route('/clear', name: 'admin_api_system_cache_clear', methods: ['POST'])]
    public function clear(Request $request): JsonResponse
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
            action: 'system.cache.attempt',
            entityType: 'system.cache',
            entityId: 'cache.clear.app',
        );

        try {
            $this->confirmation->consume($actorId, 'cache.clear.app', $confirmToken);
            $result = $this->cacheManager->clear();
            $this->auditLogger->log(
                action: 'system.cache.success',
                entityType: 'system.cache',
                entityId: 'cache.clear.app',
                newValues: ['result' => $result],
            );

            return new JsonResponse($result, ($result['exitCode'] ?? 1) === 0 ? 200 : 500);
        } catch (InvalidArgumentException $exception) {
            $this->auditLogger->log(
                action: 'system.cache.failure',
                entityType: 'system.cache',
                entityId: 'cache.clear.app',
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
