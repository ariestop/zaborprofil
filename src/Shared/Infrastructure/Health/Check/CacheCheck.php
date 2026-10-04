<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Health\Check;

use App\Shared\Infrastructure\Health\HealthCheckInterface;
use App\Shared\Infrastructure\Health\HealthCheckResult;
use Symfony\Contracts\Cache\CacheInterface;
use Throwable;

final readonly class CacheCheck implements HealthCheckInterface
{
    public function __construct(private CacheInterface $cache)
    {
    }

    public function name(): string
    {
        return 'cache';
    }

    public function label(): string
    {
        return 'Filesystem cache';
    }

    public function isRequiredForReadiness(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        try {
            $this->cache->delete('health_check');
            $this->cache->get('health_check', static fn (): string => 'ok');

            return HealthCheckResult::ok($this->name(), $this->label(), 'Cache read/write is available.');
        } catch (Throwable $exception) {
            return HealthCheckResult::fail($this->name(), $this->label(), 'Cache read/write failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
