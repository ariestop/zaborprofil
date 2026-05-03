<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Doctrine\DBAL\Connection;
use Throwable;

final readonly class DatabaseCheck implements HealthCheckInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'Database';
    }

    public function isRequiredForReadiness(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return HealthCheckResult::ok($this->name(), $this->label(), 'Database connection is available.', [
                'platform' => $this->connection->getDatabasePlatform()::class,
            ]);
        } catch (Throwable $exception) {
            return HealthCheckResult::fail($this->name(), $this->label(), 'Database connection failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
