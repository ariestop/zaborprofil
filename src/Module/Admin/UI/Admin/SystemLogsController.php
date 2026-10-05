<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\LogReaderService;
use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/logs')]
final readonly class SystemLogsController
{
    use SystemControllerTrait;

    public function __construct(
        private LogReaderService $logs,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_logs', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        $channel = $request->query->getString('channel');

        return new JsonResponse($this->logs->readLatest($channel !== '' ? $channel : null));
    }
}
