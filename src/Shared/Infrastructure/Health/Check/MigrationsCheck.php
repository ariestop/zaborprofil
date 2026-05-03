<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class MigrationsCheck implements HealthCheckInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function name(): string
    {
        return 'migrations';
    }

    public function label(): string
    {
        return 'Doctrine migrations';
    }

    public function isRequiredForReadiness(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        try {
            $schemaManager = $this->connection->createSchemaManager();
            if (!$schemaManager->tablesExist(['doctrine_migration_versions'])) {
                return HealthCheckResult::warning($this->name(), $this->label(), 'Migrations table is not created yet.');
            }

            $count = $this->connection->executeQuery('SELECT COUNT(*) FROM doctrine_migration_versions')->fetchOne();
            $executedCount = \is_numeric($count) ? (int) $count : 0;

            return HealthCheckResult::ok($this->name(), $this->label(), 'Migrations table is readable.', [
                'executed' => $executedCount,
            ]);
        } catch (Throwable $exception) {
            return HealthCheckResult::fail($this->name(), $this->label(), 'Migrations status cannot be read.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
