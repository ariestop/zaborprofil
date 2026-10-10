<?php

declare(strict_types=1);

namespace App\Module\Settings\UI\Admin;

use App\Module\Admin\Application\Service\AdminAuditLogger;
use App\Module\Admin\Application\Service\DangerousActionConfirmationService;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Settings\Application\Service\MigrationAdminServiceInterface;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\UI\Http\AdminApiErrorResponder;
use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use Doctrine\Migrations\Exception\MigrationClassNotFound;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/settings/migrations')]
final readonly class MigrationsApiController
{
    private const string AUDIT_ENTITY_TYPE = 'system.migration';

    public function __construct(
        private MigrationAdminServiceInterface $migrations,
        private DangerousActionConfirmationService $confirmation,
        private AdminAuditLogger $auditLogger,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
        private AdminApiErrorResponder $errors,
        private bool $webActionsEnabled,
        private JsonRequest $jsonRequest,
    ) {
    }

    #[Route('', name: 'admin_api_settings_migrations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)) {
            return AdminApiResponses::accessDenied();
        }

        $actionsAllowed = $this->canRunActions();

        return new JsonResponse(array_map(
            static fn (array $migration): array => [
                ...$migration,
                'canApply' => $actionsAllowed && ($migration['canApply'] ?? false) === true,
                'canRollback' => $actionsAllowed && ($migration['canRollback'] ?? false) === true,
                'actionsAllowed' => $actionsAllowed,
            ],
            $this->migrations->list(),
        ));
    }

    #[Route('/{version}/apply', name: 'admin_api_settings_migrations_apply', requirements: ['version' => '.+'], methods: ['POST'])]
    public function apply(string $version, Request $request): JsonResponse
    {
        return $this->run('apply', $version, $request, fn (): array => $this->migrations->apply($version));
    }

    #[Route('/{version}/rollback', name: 'admin_api_settings_migrations_rollback', requirements: ['version' => '.+'], methods: ['POST'])]
    public function rollback(string $version, Request $request): JsonResponse
    {
        return $this->run('rollback', $version, $request, fn (): array => $this->migrations->rollback($version));
    }

    /**
     * @param \Closure(): array<string, mixed> $action
     */
    private function run(string $operation, string $version, Request $request, \Closure $action): JsonResponse
    {
        if (!$this->webActionsEnabled) {
            return new JsonResponse([
                'error' => 'Migrations from the web admin are disabled. Run them from the CLI.',
                'code' => 'MIGRATIONS_DISABLED',
            ], 403);
        }

        if (!$this->hasDangerousPermissions()) {
            return AdminApiResponses::accessDenied();
        }

        $actorId = $this->actorId();
        if ($actorId === null) {
            return AdminApiResponses::accessDenied();
        }

        $entityId = $operation.':'.$version;
        $this->auditLogger->log(
            action: 'system.migration.attempt',
            entityType: self::AUDIT_ENTITY_TYPE,
            entityId: $entityId,
        );

        try {
            $this->confirmation->consume($actorId, 'migration.'.$entityId, $this->confirmToken($request));
            $result = $action();
            $this->auditLogger->log(
                action: 'system.migration.success',
                entityType: self::AUDIT_ENTITY_TYPE,
                entityId: $entityId,
                newValues: ['result' => $result],
            );

            return new JsonResponse($result);
        } catch (MigrationClassNotFound) {
            $this->auditFailure($entityId, 'Migration not found.');

            return $this->errors->notFound('Migration not found.');
        } catch (InvalidArgumentException $exception) {
            $this->auditFailure($entityId, $exception->getMessage());

            return $this->errors->validation($exception->getMessage());
        } catch (Throwable $exception) {
            $this->auditFailure($entityId, 'Unexpected error: '.$exception::class);

            return $this->errors->internal($exception, 'Admin migration '.$operation);
        }
    }

    private function auditFailure(string $entityId, string $reason): void
    {
        $this->auditLogger->log(
            action: 'system.migration.failure',
            entityType: self::AUDIT_ENTITY_TYPE,
            entityId: $entityId,
            newValues: ['error' => $reason],
        );
    }

    private function canRunActions(): bool
    {
        return $this->webActionsEnabled && $this->hasDangerousPermissions();
    }

    private function hasDangerousPermissions(): bool
    {
        return $this->authorizationChecker->isGranted(AdminPermission::SETTINGS_EDIT)
            && $this->authorizationChecker->isGranted(AdminPermission::SYSTEM_MANAGE)
            && $this->authorizationChecker->isGranted(AdminPermission::SYSTEM_DANGEROUS);
    }

    private function confirmToken(Request $request): string
    {
        $token = $this->jsonRequest->lenientPayload($request)['confirmToken'] ?? null;

        return \is_string($token) ? $token : '';
    }

    private function actorId(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }

}
