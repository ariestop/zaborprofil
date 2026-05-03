<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class DiskSpaceCheck implements HealthCheckInterface
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function name(): string
    {
        return 'disk';
    }

    public function label(): string
    {
        return 'Disk space';
    }

    public function isRequiredForReadiness(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $path = $this->kernel->getProjectDir();
        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return HealthCheckResult::warning($this->name(), $this->label(), 'Disk usage cannot be calculated.');
        }

        $usedPercent = round((($total - $free) / $total) * 100, 2);
        $details = [
            'usedPercent' => $usedPercent,
            'freeBytes' => $free,
            'totalBytes' => $total,
        ];

        if ($usedPercent >= 90.0 && $free < 1_073_741_824) {
            return HealthCheckResult::fail($this->name(), $this->label(), 'Disk usage is critical.', $details);
        }

        if ($usedPercent >= 80.0) {
            return HealthCheckResult::warning($this->name(), $this->label(), 'Disk usage is high.', $details);
        }

        return HealthCheckResult::ok($this->name(), $this->label(), 'Disk space is sufficient.', $details);
    }
}
