<?php

declare(strict_types=1);

namespace App\Module\Settings\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Module\Settings\Application\Service\SettingsService;
use App\Module\Settings\Domain\Entity\Setting;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/settings')]
final readonly class SettingsApiController
{
    public function __construct(
        private SettingsService $settings,
        private AuthorizationCheckerInterface $authorizationChecker,
        private PublicPageCacheInvalidator $publicPageCache,
    ) {
    }

    #[Route('', name: 'admin_api_settings_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        $scope = $request->query->get('scope');
        $scope = \is_string($scope) && $scope !== '' ? $scope : null;

        return new JsonResponse(array_map(
            self::settingToArray(...),
            $this->settings->all($scope),
        ));
    }

    #[Route('/{scope}/{key}', name: 'admin_api_settings_upsert', requirements: ['scope' => '[a-z][a-z0-9_.-]+', 'key' => '[a-z][a-z0-9_.-]+'], methods: ['PUT'])]
    public function upsert(string $scope, string $key, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->payload($request);
            if (!\array_key_exists('value', $payload)) {
                throw new InvalidArgumentException('Field "value" is required.');
            }

            $description = $payload['description'] ?? null;
            if ($description !== null && !\is_string($description)) {
                throw new InvalidArgumentException('Field "description" must be a string or null.');
            }

            $setting = $this->settings->set($scope, $key, $payload['value'], $description);
            $this->publicPageCache->invalidateAll();

            return new JsonResponse(self::settingToArray($setting));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/{scope}/{key}', name: 'admin_api_settings_delete', requirements: ['scope' => '[a-z][a-z0-9_.-]+', 'key' => '[a-z][a-z0-9_.-]+'], methods: ['DELETE'])]
    public function delete(string $scope, string $key): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return $this->accessDenied();
        }

        $this->settings->delete($scope, $key);
        $this->publicPageCache->invalidateAll();

        return new JsonResponse(null, 204);
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

    /**
     * @return array<string, mixed>
     */
    private static function settingToArray(Setting $setting): array
    {
        return [
            'id' => (string) $setting->id(),
            'scope' => $setting->scope(),
            'key' => $setting->key(),
            'value' => $setting->value(),
            'description' => $setting->description(),
            'updatedAt' => $setting->updatedAt()->format(DATE_ATOM),
        ];
    }
}
