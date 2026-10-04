<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use DateTimeImmutable;

/**
 * Почасовой счётчик HTTP 5xx на файловой системе (без БД и Redis): он должен работать, даже если база недоступна.
 * Хранится в каталоге логов (`var/log/observability`), а не в `var/cache`: он общий между релизами и не сбрасывается деплоем.
 */
final readonly class ServerErrorCounter
{
    private const int RETENTION_HOURS = 72;

    private const string FILE_NAME = 'http-5xx.json';

    public function __construct(private string $directory)
    {
    }

    public function increment(?DateTimeImmutable $now = null): void
    {
        $now ??= new DateTimeImmutable();

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            return;
        }

        $handle = @fopen($this->directory.'/'.self::FILE_NAME, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, \LOCK_EX)) {
                return;
            }

            $buckets = $this->decode((string) stream_get_contents($handle));
            $key = $this->bucketKey($now);
            $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            $buckets = $this->prune($buckets, $now);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($buckets, \JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, \LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function countLastHours(int $hours, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable();
        $path = $this->directory.'/'.self::FILE_NAME;
        $contents = is_file($path) ? @file_get_contents($path) : false;
        if ($contents === false) {
            return 0;
        }

        $buckets = $this->decode($contents);
        $total = 0;
        for ($offset = 0; $offset < $hours; ++$offset) {
            $total += $buckets[$this->bucketKey($now->modify(\sprintf('-%d hours', $offset)))] ?? 0;
        }

        return $total;
    }

    /**
     * @return array<int, int>
     */
    private function decode(string $contents): array
    {
        $decoded = json_decode($contents, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $buckets = [];
        foreach ($decoded as $key => $value) {
            if (\is_int($key) && \is_int($value)) {
                $buckets[$key] = $value;
            }
        }

        return $buckets;
    }

    /**
     * @param array<int, int> $buckets
     *
     * @return array<int, int>
     */
    private function prune(array $buckets, DateTimeImmutable $now): array
    {
        $oldest = $this->bucketKey($now->modify(\sprintf('-%d hours', self::RETENTION_HOURS)));

        return array_filter($buckets, static fn (int $key): bool => $key >= $oldest, \ARRAY_FILTER_USE_KEY);
    }

    private function bucketKey(DateTimeImmutable $moment): int
    {
        return (int) $moment->setTimezone(new \DateTimeZone('UTC'))->format('YmdH');
    }
}
