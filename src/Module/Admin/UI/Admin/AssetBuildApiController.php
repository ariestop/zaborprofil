<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\AssetBuildRunner;
use App\Module\Auth\Domain\Security\AdminPermission;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/assets/build')]
final readonly class AssetBuildApiController
{
    public function __construct(
        private AssetBuildRunner $buildRunner,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_assets_build_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->buildRunner->status());
    }

    #[Route('/run', name: 'admin_api_system_assets_build_run', methods: ['POST'])]
    public function run(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->badRequest('Invalid JSON payload.');
        }

        if ($payload === null) {
            $payload = [];
        }

        if (!\is_array($payload)) {
            return $this->badRequest('Invalid request payload.');
        }

        $targets = $payload['targets'] ?? [];
        if (!\is_array($targets)) {
            return $this->badRequest('Field "targets" must be an array.');
        }

        $status = $this->buildRunner->start($targets);

        return new JsonResponse($status, $status['status'] === 'running' ? 202 : 200);
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
            'code' => 'INVALID_REQUEST',
        ], 400);
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
