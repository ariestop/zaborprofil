<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Symfony\Component\HttpKernel\KernelInterface;

final readonly class LogReaderService
{
    private const int MAX_BYTES = 16000;

    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function readLatest(?string $channel = null): array
    {
        $logFile = $this->resolveLogFile($channel);
        $content = '';
        if (is_file($logFile)) {
            $size = filesize($logFile);
            if (\is_int($size) && $size > self::MAX_BYTES) {
                $handle = fopen($logFile, 'rb');
                if (\is_resource($handle)) {
                    fseek($handle, -self::MAX_BYTES, \SEEK_END);
                    $content = '[...обрезано...]'."\n".(string) stream_get_contents($handle);
                    fclose($handle);
                }
            } else {
                $content = (string) file_get_contents($logFile);
            }
        }

        return [
            'channel' => $channel ?? 'app',
            'path' => $logFile,
            'content' => $content,
            'readAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    private function resolveLogFile(?string $channel): string
    {
        $base = $this->kernel->getProjectDir().'/var/log';
        if ($channel === null || trim($channel) === '') {
            return $base.'/app.log';
        }

        return $base.'/'.preg_replace('/[^a-z0-9_-]/i', '', $channel).'.log';
    }
}
