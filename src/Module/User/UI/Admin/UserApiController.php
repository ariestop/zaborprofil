<?php

declare(strict_types=1);

namespace App\Module\User\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/users')]
final readonly class UserApiController
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private AdminUserRepository $users,
    ) {
    }

    #[Route('', name: 'admin_api_users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'users' => array_map(
                static fn (AdminUser $user): array => self::serializeUser($user),
                $this->users->findBy([], ['createdAt' => 'DESC']),
            ),
        ]);
    }

    #[Route('/{id}/roles', name: 'admin_api_users_roles_update', methods: ['PATCH'])]
    public function updateRoles(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $user = $this->users->find($id);
            if (!$user instanceof AdminUser) {
                return new JsonResponse(['error' => 'User not found.'], 404);
            }

            $payload = $request->getPayload()->all();
            if (!isset($payload['roles']) || !\is_array($payload['roles'])) {
                return new JsonResponse([
                    'error' => 'Validation failed.',
                    'details' => [
                        ['field' => 'roles', 'message' => 'Поле roles обязательно и должно быть массивом.'],
                    ],
                ], 422);
            }

            $roles = array_values(array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), $payload['roles']),
                static fn (string $value): bool => $value !== '',
            ));

            if ($roles === []) {
                return new JsonResponse([
                    'error' => 'Validation failed.',
                    'details' => [
                        ['field' => 'roles', 'message' => 'Нужно выбрать хотя бы одну роль.'],
                    ],
                ], 422);
            }

            $user->updateRoles($roles);
            $this->users->save($user);

            return new JsonResponse(self::serializeUser($user));
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    private static function serializeUser(AdminUser $user): array
    {
        return [
            'id' => (string) $user->id(),
            'email' => $user->email(),
            'roles' => $user->getRoles(),
            'active' => $user->isActive(),
            'createdAt' => $user->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->updatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
