<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\Service\SystemWarningCollector;
use App\Module\Auth\Domain\Security\AdminPermission;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use App\Shared\Infrastructure\Health\HealthRegistry;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/api/system/health')]
final readonly class SystemHealthApiController
{
    public function __construct(
        private HealthRegistry $healthRegistry,
        private SystemWarningCollector $warnings,
        private AuthorizationCheckerInterface $authorizationChecker,
        private KernelInterface $kernel,
        private Connection $connection,
    ) {
    }

    #[Route('', name: 'admin_api_system_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::SYSTEM_VIEW)) {
            return new JsonResponse([
                'error' => 'Access denied.',
                'code' => 'ACCESS_DENIED',
            ], 403);
        }

        $checks = $this->healthRegistry->runAll();

        return new JsonResponse([
            'status' => $this->healthRegistry->isHealthy($checks) ? 'ok' : 'error',
            'environment' => [
                'appEnv' => $this->kernel->getEnvironment(),
                'appDebug' => $this->kernel->isDebug(),
                'phpVersion' => PHP_VERSION,
                'databasePlatform' => $this->connection->getDatabasePlatform()::class,
            ],
            'checks' => array_map(static fn (HealthCheckResult $result): array => $result->toArray(), $checks),
            'warnings' => $this->warnings->collect(),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
