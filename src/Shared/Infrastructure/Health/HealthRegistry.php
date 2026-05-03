<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health;

final class HealthRegistry
{
    /**
     * @var list<HealthCheckInterface>
     */
    private array $checks;

    /**
     * @param iterable<HealthCheckInterface> $checks
     */
    public function __construct(iterable $checks)
    {
        $this->checks = \is_array($checks) ? array_values($checks) : iterator_to_array($checks, false);
    }

    /**
     * @return list<HealthCheckResult>
     */
    public function runAll(): array
    {
        return array_map(
            static fn (HealthCheckInterface $check): HealthCheckResult => $check->run(),
            $this->checks,
        );
    }

    /**
     * @return list<HealthCheckResult>
     */
    public function runReadiness(): array
    {
        return array_map(
            static fn (HealthCheckInterface $check): HealthCheckResult => $check->run(),
            array_values(array_filter(
                $this->checks,
                static fn (HealthCheckInterface $check): bool => $check->isRequiredForReadiness(),
            )),
        );
    }

    /**
     * @param list<HealthCheckResult> $results
     */
    public function isHealthy(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result->isHealthy()) {
                return false;
            }
        }

        return true;
    }
}
