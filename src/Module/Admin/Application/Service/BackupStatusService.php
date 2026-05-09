<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class BackupStatusService
{
    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $backupDir = $this->kernel->getProjectDir().'/var/backups';
        $latestBackup = null;
        $files = [];

        if (is_dir($backupDir)) {
            $list = scandir($backupDir);
            if (\is_array($list)) {
                foreach ($list as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }

                    $path = $backupDir.'/'.$item;
                    if (!is_file($path)) {
                        continue;
                    }

                    $mtime = filemtime($path) ?: 0;
                    $files[] = [
                        'name' => $item,
                        'size' => filesize($path) ?: 0,
                        'modifiedAt' => $mtime > 0 ? date(DATE_ATOM, $mtime) : null,
                    ];
                }
            }
        }

        usort($files, static fn (array $left, array $right): int => strcmp((string) ($right['modifiedAt'] ?? ''), (string) ($left['modifiedAt'] ?? '')));
        if ($files !== []) {
            $latestBackup = $files[0];
        }

        return [
            'backupDirectory' => $backupDir,
            'latestBackup' => $latestBackup,
            'files' => array_slice($files, 0, 20),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
