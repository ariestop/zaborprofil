<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use Monolog\LogRecord;

final class ReleaseProcessor
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $releaseInfo = null;

    public function __construct(
        private readonly string $environment,
        private readonly string $releaseInfoPath,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $releaseInfo = $this->releaseInfo();

        $record->extra['environment'] = $this->environment;
        $record->extra['release'] = $releaseInfo['release'] ?? null;
        $record->extra['commit'] = $releaseInfo['commit'] ?? null;

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseInfo(): array
    {
        if ($this->releaseInfo !== null) {
            return $this->releaseInfo;
        }

        if (!is_file($this->releaseInfoPath)) {
            return $this->releaseInfo = [];
        }

        $decoded = json_decode((string) file_get_contents($this->releaseInfoPath), true);
        if (!\is_array($decoded)) {
            return $this->releaseInfo = [];
        }

        $releaseInfo = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $releaseInfo[$key] = $value;
            }
        }

        return $this->releaseInfo = $releaseInfo;
    }
}
