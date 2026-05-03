<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Logging;

use App\Shared\Application\Logging\BusinessEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class BusinessEventLoggerTest extends TestCase
{
    public function testLogsBusinessEventWithEventContext(): void
    {
        $logger = new CollectingLogger();
        $businessEvents = new BusinessEventLogger($logger);

        $businessEvents->log('page.published', ['page_id' => '01']);

        self::assertSame('page.published', $logger->records[0]['message'] ?? null);
        self::assertSame('page.published', $logger->records[0]['context']['event'] ?? null);
        self::assertSame('01', $logger->records[0]['context']['page_id'] ?? null);
    }
}

final class CollectingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: mixed, message: string|\Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
