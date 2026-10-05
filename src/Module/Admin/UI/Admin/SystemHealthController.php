<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\SystemHealthService;
use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system')]
final readonly class SystemHealthController
{
    use SystemControllerTrait;

    public function __construct(
        private SystemHealthService $healthService,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/overview', name: 'admin_api_system_overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            ...$this->healthService->overview(),
            'canManageDangerousActions' => $this->authorizationChecker->isGranted(AdminPermission::SYSTEM_DANGEROUS),
        ]);
    }
}
