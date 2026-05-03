<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;

final readonly class AppCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'app';
    }

    public function label(): string
    {
        return 'Application';
    }

    public function isRequiredForReadiness(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        return HealthCheckResult::ok($this->name(), $this->label(), 'Application is reachable.');
    }
}
