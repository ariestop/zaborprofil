<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Shared\Infrastructure\Health\HealthCheckResult;
use App\Shared\Infrastructure\Health\HealthRegistry;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class SystemHealthService
{
    public function __construct(
        private HealthRegistry $healthRegistry,
        private SystemWarningCollector $warnings,
        private KernelInterface $kernel,
        private Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $checks = $this->healthRegistry->runAll();

        return [
            'status' => $this->healthRegistry->isHealthy($checks) ? 'ok' : 'error',
            'environment' => [
                'appEnv' => $this->kernel->getEnvironment(),
                'appDebug' => $this->kernel->isDebug(),
                'phpVersion' => PHP_VERSION,
                'databasePlatform' => $this->connection->getDatabasePlatform()::class,
            ],
            'checks' => array_map(
                static fn (HealthCheckResult $result): array => $result->toArray(),
                $checks,
            ),
            'warnings' => $this->warnings->collect(),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
