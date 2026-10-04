<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Shared\Infrastructure\Health\Check\DiskSpaceCheck;
use App\Shared\Infrastructure\Observability\ServerErrorCounter;
use DateTimeImmutable;

final readonly class ObservabilitySnapshotService
{
    public function __construct(
        private ServerErrorCounter $serverErrors,
        private QueueMonitorService $queues,
        private DiskSpaceCheck $disk,
    ) {
    }

    /**
     * @return array{
     *     serverErrors: array{lastHour: int, last24Hours: int},
     *     queue: array{pending: int, failed: int},
     *     disk: array{status: string, freeBytes: int|null, totalBytes: int|null, usedPercent: float|null},
     *     checkedAt: string
     * }
     */
    public function snapshot(): array
    {
        $queueStatus = $this->queues->status();
        $transports = \is_array($queueStatus['transports'] ?? null) ? $queueStatus['transports'] : [];
        $diskResult = $this->disk->run();
        $details = $diskResult->details;

        return [
            'serverErrors' => [
                'lastHour' => $this->serverErrors->countLastHours(1),
                'last24Hours' => $this->serverErrors->countLastHours(24),
            ],
            'queue' => [
                'pending' => $this->intValue($transports['async'] ?? 0),
                'failed' => $this->intValue($transports['failed'] ?? 0),
            ],
            'disk' => [
                'status' => $diskResult->status,
                'freeBytes' => isset($details['freeBytes']) ? $this->intValue($details['freeBytes']) : null,
                'totalBytes' => isset($details['totalBytes']) ? $this->intValue($details['totalBytes']) : null,
                'usedPercent' => isset($details['usedPercent']) && is_numeric($details['usedPercent']) ? (float) $details['usedPercent'] : null,
            ],
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
