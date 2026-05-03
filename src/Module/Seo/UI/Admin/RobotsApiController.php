<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Seo\Application\Service\RobotsTxtManager;
use App\Module\Settings\Application\Service\SettingsService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/seo/robots')]
final readonly class RobotsApiController
{
    public function __construct(
        private RobotsTxtManager $robots,
        private SettingsService $settings,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_seo_robots_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'body' => $this->robots->editableBody(),
            'effectiveBody' => $this->robots->body(),
            'scope' => RobotsTxtManager::SCOPE,
            'key' => RobotsTxtManager::KEY,
        ]);
    }

    #[Route('', name: 'admin_api_seo_robots_update', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SEO_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->payload($request);
            $body = $payload['body'] ?? null;
            if ($body !== null && !\is_string($body)) {
                throw new InvalidArgumentException('Field "body" must be a string or null.');
            }

            $normalized = $this->robots->normalizeEditableBody($body);
            if ($normalized === null) {
                $this->settings->delete(RobotsTxtManager::SCOPE, RobotsTxtManager::KEY);
            } else {
                $this->settings->set(RobotsTxtManager::SCOPE, RobotsTxtManager::KEY, $normalized, 'Custom robots.txt content.');
            }

            return $this->show();
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

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('Request body must be a JSON object.');
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException('Request body must be a JSON object.');
            }

            $payload[$key] = $value;
        }

        return $payload;
    }
}
