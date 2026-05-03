<?php

declare(strict_types=1);

namespace App\Module\Settings\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Settings\Application\Service\MigrationAdminService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/settings/migrations')]
final readonly class MigrationsApiController
{
    public function __construct(
        private MigrationAdminService $migrations,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_settings_migrations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->migrations->list());
    }

    #[Route('/{version}/apply', name: 'admin_api_settings_migrations_apply', requirements: ['version' => '.+'], methods: ['POST'])]
    public function apply(string $version): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($this->migrations->apply($version));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/{version}/rollback', name: 'admin_api_settings_migrations_rollback', requirements: ['version' => '.+'], methods: ['POST'])]
    public function rollback(string $version): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        try {
            return new JsonResponse($this->migrations->rollback($version));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
