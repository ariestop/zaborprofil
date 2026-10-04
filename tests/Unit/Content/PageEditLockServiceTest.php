<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\PageEditLockService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

final class PageEditLockServiceTest extends TestCase
{
    public function testFirstSessionOwnsLockAndSecondOneSeesHolder(): void
    {
        [$service] = $this->service();

        $first = $service->acquire('page-1', 'u1', 'anna@example.test', 'session-aaaa', false);
        self::assertTrue($first->ownedByCurrentSession);

        $second = $service->acquire('page-1', 'u2', 'boris@example.test', 'session-bbbb', false);
        self::assertFalse($second->ownedByCurrentSession);
        self::assertSame('anna@example.test', $second->holder?->label);
        $payload = $second->toArray();
        self::assertTrue($payload['locked']);
        self::assertIsArray($payload['holder']);
        self::assertFalse($payload['holder']['isSelf']);
    }

    public function testHeartbeatKeepsLockAndExpiredLockIsTakenFreely(): void
    {
        [$service, $clock] = $this->service();
        $service->acquire('page-1', 'u1', 'anna@example.test', 'session-aaaa', false);

        $clock->sleep(PageEditLockService::TTL_SECONDS - 10);
        self::assertTrue($service->acquire('page-1', 'u1', 'anna@example.test', 'session-aaaa', false)->ownedByCurrentSession);

        $clock->sleep(PageEditLockService::TTL_SECONDS - 10);
        self::assertFalse($service->acquire('page-1', 'u2', 'boris@example.test', 'session-bbbb', false)->ownedByCurrentSession);

        $clock->sleep(PageEditLockService::TTL_SECONDS + 1);
        self::assertNull($service->status('page-1', 'u2', 'session-bbbb')->holder);
        self::assertTrue($service->acquire('page-1', 'u2', 'boris@example.test', 'session-bbbb', false)->ownedByCurrentSession);
    }

    public function testTakeOverAndReleaseRespectOwnership(): void
    {
        [$service] = $this->service();
        $service->acquire('page-1', 'u1', 'anna@example.test', 'session-aaaa', false);

        self::assertTrue($service->acquire('page-1', 'u2', 'boris@example.test', 'session-bbbb', true)->ownedByCurrentSession);

        $service->release('page-1', 'session-aaaa');
        self::assertSame('boris@example.test', $service->status('page-1', 'u1', 'session-aaaa')->holder?->label);

        $service->release('page-1', 'session-bbbb');
        self::assertNull($service->status('page-1', 'u1', 'session-aaaa')->holder);
    }

    /**
     * @return array{PageEditLockService, MockClock}
     */
    private function service(): array
    {
        $clock = new MockClock('2026-10-04 12:00:00');

        return [new PageEditLockService(new ArrayAdapter(), $clock), $clock];
    }
}
