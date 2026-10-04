<?php

declare(strict_types=1);

namespace App\Module\User\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\User\Application\Service\AdminUserService;
use App\Module\User\Domain\AdminRole;
use App\Module\User\Domain\Exception\UserManagementException;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Module\User\Infrastructure\Repository\AdminUserRepository;
use App\Shared\UI\Http\AdminApiErrorResponder;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Ulid;
use Throwable;

#[Route('/admin/api/users')]
final readonly class UserApiController
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
        private AdminUserRepository $users,
        private AdminUserService $service,
        private AdminApiErrorResponder $errors,
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
                self::serializeUser(...),
                $this->users->findBy([], ['createdAt' => 'DESC']),
            ),
            'availableRoles' => AdminRole::all(),
        ]);
    }

    #[Route('', name: 'admin_api_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        $payload = $request->getPayload()->all();
        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;
        if (!\is_string($email) || !\is_string($password)) {
            return $this->validationError([
                ['field' => 'email', 'message' => 'Поля email и password обязательны и должны быть строками.'],
            ]);
        }

        $roles = $this->parseRoles($payload['roles'] ?? [AdminRole::EDITOR]);
        if ($roles instanceof JsonResponse) {
            return $roles;
        }

        $actor = $this->actor();
        if (!$actor instanceof AdminUser) {
            return $this->accessDenied();
        }

        if (\in_array(AdminRole::SUPER_ADMIN, $roles, true) && !\in_array(AdminRole::SUPER_ADMIN, $actor->storedRoles(), true)) {
            return $this->accessDenied();
        }

        try {
            $user = $this->service->create($email, $password, $roles);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(self::serializeUser($user), 201);
    }

    #[Route('/me/password', name: 'admin_api_users_change_own_password', methods: ['POST'])]
    public function changeOwnPassword(Request $request): JsonResponse
    {
        $actor = $this->actor();
        if (!$actor instanceof AdminUser) {
            return $this->accessDenied();
        }

        $payload = $request->getPayload()->all();
        $current = $payload['currentPassword'] ?? null;
        $password = $payload['password'] ?? null;
        if (!\is_string($current) || !\is_string($password)) {
            return $this->validationError([
                ['field' => 'password', 'message' => 'Поля currentPassword и password обязательны и должны быть строками.'],
            ]);
        }

        try {
            $this->service->changeOwnPassword($actor, $current, $password);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/{id}/roles', name: 'admin_api_users_roles_update', methods: ['PATCH'])]
    public function updateRoles(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        $target = $this->findUser($id);
        $actor = $this->actor();
        if (!$target instanceof AdminUser) {
            return $this->notFound();
        }

        if (!$actor instanceof AdminUser) {
            return $this->accessDenied();
        }

        $payload = $request->getPayload()->all();
        if (!isset($payload['roles'])) {
            return $this->validationError([
                ['field' => 'roles', 'message' => 'Поле roles обязательно и должно быть массивом.'],
            ]);
        }

        $roles = $this->parseRoles($payload['roles']);
        if ($roles instanceof JsonResponse) {
            return $roles;
        }

        try {
            $this->service->updateRoles($actor, $target, $roles);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(self::serializeUser($target));
    }

    #[Route('/{id}/password', name: 'admin_api_users_password_update', methods: ['POST'])]
    public function resetPassword(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        $target = $this->findUser($id);
        if (!$target instanceof AdminUser) {
            return $this->notFound();
        }

        $password = $request->getPayload()->all()['password'] ?? null;
        if (!\is_string($password)) {
            return $this->validationError([
                ['field' => 'password', 'message' => 'Поле password обязательно и должно быть строкой.'],
            ]);
        }

        try {
            $this->service->changePassword($target, $password);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(self::serializeUser($target));
    }

    #[Route('/{id}/active', name: 'admin_api_users_active_update', methods: ['PATCH'])]
    public function updateActive(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        $target = $this->findUser($id);
        $actor = $this->actor();
        if (!$target instanceof AdminUser) {
            return $this->notFound();
        }

        if (!$actor instanceof AdminUser) {
            return $this->accessDenied();
        }

        $active = $request->getPayload()->all()['active'] ?? null;
        if (!\is_bool($active)) {
            return $this->validationError([
                ['field' => 'active', 'message' => 'Поле active обязательно и должно быть boolean.'],
            ]);
        }

        try {
            $this->service->setActive($actor, $target, $active);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(self::serializeUser($target));
    }

    #[Route('/{id}', name: 'admin_api_users_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::USERS_MANAGE)) {
            return $this->accessDenied();
        }

        $target = $this->findUser($id);
        $actor = $this->actor();
        if (!$target instanceof AdminUser) {
            return $this->notFound();
        }

        if (!$actor instanceof AdminUser) {
            return $this->accessDenied();
        }

        try {
            $this->service->delete($actor, $target);
        } catch (UserManagementException $exception) {
            return $this->domainError($exception);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Admin User API');
        }

        return new JsonResponse(null, 204);
    }

    /**
     * @return list<string>|JsonResponse
     */
    private function parseRoles(mixed $value): array|JsonResponse
    {
        if (!\is_array($value)) {
            return $this->validationError([
                ['field' => 'roles', 'message' => 'Поле roles обязательно и должно быть массивом.'],
            ]);
        }

        $roles = [];
        foreach ($value as $role) {
            if (!\is_string($role)) {
                return $this->validationError([
                    ['field' => 'roles', 'message' => 'Каждая роль должна быть строкой.'],
                ]);
            }

            $roles[] = $role;
        }

        return $roles;
    }

    private function findUser(string $id): ?AdminUser
    {
        try {
            $ulid = Ulid::fromString($id);
        } catch (InvalidArgumentException) {
            return null;
        }

        $user = $this->users->find($ulid);

        return $user instanceof AdminUser ? $user : null;
    }

    private function actor(): ?AdminUser
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? $user : null;
    }

    private function notFound(): JsonResponse
    {
        return $this->errors->notFound('User not found.');
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    private function validationError(array $details): JsonResponse
    {
        return $this->errors->validation('Validation failed.', details: $details);
    }

    private function domainError(UserManagementException $exception): JsonResponse
    {
        if ($exception->errorCode === UserManagementException::VALIDATION || $exception->errorCode === UserManagementException::INVALID_CURRENT_PASSWORD) {
            return $this->validationError($exception->details !== [] ? $exception->details : [['field' => 'roles', 'message' => $exception->getMessage()]]);
        }

        return new JsonResponse([
            'error' => $exception->getMessage(),
            'code' => $exception->errorCode,
        ], $exception->errorCode === UserManagementException::ROLE_NOT_ALLOWED ? 403 : 409);
    }

    /**
     * @return array{id: string, email: string, roles: list<string>, active: bool, createdAt: string, updatedAt: string}
     */
    private static function serializeUser(AdminUser $user): array
    {
        return [
            'id' => (string) $user->id(),
            'email' => $user->email(),
            'roles' => $user->storedRoles(),
            'active' => $user->isActive(),
            'createdAt' => $user->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->updatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
