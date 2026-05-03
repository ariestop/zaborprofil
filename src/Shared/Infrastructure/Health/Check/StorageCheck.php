<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class StorageCheck implements HealthCheckInterface
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function name(): string
    {
        return 'storage';
    }

    public function label(): string
    {
        return 'Writable storage';
    }

    public function isRequiredForReadiness(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $projectDir = $this->kernel->getProjectDir();
        $directories = [
            'cache' => $projectDir.'/var/cache',
            'log' => $projectDir.'/var/log',
            'uploads' => $projectDir.'/public_html/uploads',
        ];

        foreach ($directories as $name => $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                return HealthCheckResult::fail($this->name(), $this->label(), 'Directory cannot be created.', [
                    'directory' => $name,
                    'path' => $directory,
                ]);
            }

            if (!is_writable($directory)) {
                return HealthCheckResult::fail($this->name(), $this->label(), 'Directory is not writable.', [
                    'directory' => $name,
                    'path' => $directory,
                ]);
            }
        }

        return HealthCheckResult::ok($this->name(), $this->label(), 'Required directories are writable.');
    }
}
