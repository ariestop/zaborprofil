<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Shared\Infrastructure\Maintenance\MaintenanceState;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/maintenance')]
final readonly class MaintenanceApiController
{
    public function __construct(
        private MaintenanceState $maintenance,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_system_maintenance_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse($this->maintenance->payload());
    }

    #[Route('/on', name: 'admin_api_system_maintenance_on', methods: ['POST'])]
    public function on(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)) {
            return $this->accessDenied();
        }

        $payload = $this->payload($request);
        $message = $payload['message'] ?? 'Сайт временно находится на техническом обслуживании.';
        $allowedIps = $payload['allowedIps'] ?? [];

        if (!\is_string($message) || !\is_array($allowedIps)) {
            throw new InvalidArgumentException('Invalid maintenance payload.');
        }

        $this->maintenance->enable($message, array_values(array_filter($allowedIps, \is_string(...))));

        return new JsonResponse($this->maintenance->payload());
    }

    #[Route('/off', name: 'admin_api_system_maintenance_off', methods: ['POST'])]
    public function off(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)) {
            return $this->accessDenied();
        }

        $this->maintenance->disable();

        return new JsonResponse($this->maintenance->payload());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);
        if (!\is_array($decoded)) {
            return [];
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
