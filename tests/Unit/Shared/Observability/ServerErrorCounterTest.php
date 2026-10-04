<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Observability;

use App\Shared\Infrastructure\Observability\ServerErrorCounter;
use App\Shared\Infrastructure\Observability\ServerErrorCounterSubscriber;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ServerErrorCounterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/zaborprofil-counter-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testCountsErrorsInRollingHourWindows(): void
    {
        $counter = new ServerErrorCounter($this->directory);
        $now = new DateTimeImmutable('2026-10-04 12:30:00 UTC');

        $counter->increment($now->modify('-26 hours'));
        $counter->increment($now->modify('-5 hours'));
        $counter->increment($now->modify('-30 minutes'));
        $counter->increment($now);

        self::assertSame(2, $counter->countLastHours(1, $now));
        self::assertSame(3, $counter->countLastHours(24, $now));
        self::assertSame(4, $counter->countLastHours(48, $now));
    }

    public function testReturnsZeroWithoutStorage(): void
    {
        self::assertSame(0, (new ServerErrorCounter($this->directory))->countLastHours(24));
    }

    public function testPrunesBucketsOlderThanRetention(): void
    {
        $counter = new ServerErrorCounter($this->directory);
        $now = new DateTimeImmutable('2026-10-04 12:00:00 UTC');

        $counter->increment($now->modify('-100 hours'));
        $counter->increment($now);

        self::assertSame(1, $counter->countLastHours(200, $now));
    }

    public function testSubscriberCountsOnlyMainRequestServerErrorsOutsideHealth(): void
    {
        $counter = new ServerErrorCounter($this->directory);
        $subscriber = new ServerErrorCounterSubscriber($counter);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $subscriber->onKernelResponse(new ResponseEvent($kernel, Request::create('/admin/api/pages'), HttpKernelInterface::MAIN_REQUEST, new Response('', 500)));
        $subscriber->onKernelResponse(new ResponseEvent($kernel, Request::create('/admin/api/pages'), HttpKernelInterface::MAIN_REQUEST, new Response('', 404)));
        $subscriber->onKernelResponse(new ResponseEvent($kernel, Request::create('/health'), HttpKernelInterface::MAIN_REQUEST, new Response('', 503)));
        $subscriber->onKernelResponse(new ResponseEvent($kernel, Request::create('/x'), HttpKernelInterface::SUB_REQUEST, new Response('', 500)));

        self::assertSame(1, $counter->countLastHours(1));
    }
}
