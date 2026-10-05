<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\BackupStatusService;
use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/backups')]
final readonly class SystemBackupController
{
    use SystemControllerTrait;

    public function __construct(
        private BackupStatusService $backupStatus,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_backups', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->backupStatus->status());
    }
}
