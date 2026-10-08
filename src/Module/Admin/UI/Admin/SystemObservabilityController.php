<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\ObservabilitySnapshotService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Shared\UI\Http\AdminApiResponses;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/observability')]
final readonly class SystemObservabilityController
{
    use SystemControllerTrait;

    public function __construct(
        private ObservabilitySnapshotService $snapshot,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_observability', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return AdminApiResponses::accessDenied();
        }

        return new JsonResponse($this->snapshot->snapshot());
    }
}
