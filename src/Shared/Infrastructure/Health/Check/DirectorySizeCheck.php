<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class DirectorySizeCheck implements HealthCheckInterface
{
    public function __construct(private KernelInterface $kernel)
    {
    }

    public function name(): string
    {
        return 'directories';
    }

    public function label(): string
    {
        return 'Logs and uploads size';
    }

    public function isRequiredForReadiness(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        $projectDir = $this->kernel->getProjectDir();

        return HealthCheckResult::ok($this->name(), $this->label(), 'Directory sizes calculated.', [
            'logsBytes' => $this->directorySize($projectDir.'/var/log'),
            'uploadsBytes' => $this->directorySize($projectDir.'/public_html/uploads'),
        ]);
    }

    private function directorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
