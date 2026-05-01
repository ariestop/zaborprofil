<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

final readonly class HealthCheckController
{
    public function __construct(
        private Connection $connection,
        private CacheInterface $cache,
        private KernelInterface $kernel,
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => 'ok',
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedisBackedCache(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = !\in_array('fail', $checks, true);

        return new JsonResponse([
            'status' => $isHealthy ? 'ok' : 'error',
            ...$checks,
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], $isHealthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }

    private function checkDatabase(): string
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
            $this->checkMigrationsTableIfAvailable();

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function checkMigrationsTableIfAvailable(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['doctrine_migration_versions'])) {
            return;
        }

        $this->connection->executeQuery('SELECT COUNT(*) FROM doctrine_migration_versions')->fetchOne();
    }

    private function checkRedisBackedCache(): string
    {
        try {
            $this->cache->delete('health_check');
            $this->cache->get('health_check', static fn (): string => 'ok');

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function checkStorage(): string
    {
        $projectDir = $this->kernel->getProjectDir();
        $directories = [
            $projectDir . '/var/cache',
            $projectDir . '/var/log',
            $projectDir . '/public_html/uploads',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                return 'fail';
            }

            if (!is_writable($directory)) {
                return 'fail';
            }
        }

        return 'ok';
    }
}
