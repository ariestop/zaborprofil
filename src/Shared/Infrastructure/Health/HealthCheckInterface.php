<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check')]
interface HealthCheckInterface
{
    public function name(): string;

    public function label(): string;

    public function isRequiredForReadiness(): bool;

    public function run(): HealthCheckResult;
}
