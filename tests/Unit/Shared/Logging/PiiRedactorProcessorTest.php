<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Logging;

use App\Shared\Infrastructure\Logging\Processor\PiiRedactorProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class PiiRedactorProcessorTest extends TestCase
{
    public function testRedactsSensitiveContextAndMessageValues(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Error,
            message: 'Lead from user@example.com and +7 999 123-45-67',
            context: [
                'email' => 'user@example.com',
                'phone' => '+7 999 123-45-67',
                'nested' => ['ip' => '192.168.1.10'],
            ],
            extra: [],
        );

        $processed = (new PiiRedactorProcessor())($record);

        self::assertSame('Lead from [email] and [phone]', $processed->message);
        self::assertSame('[redacted]', $processed->context['email']);
        self::assertSame('[redacted]', $processed->context['phone']);
        self::assertIsArray($processed->context['nested']);
        self::assertSame('[ip]', $processed->context['nested']['ip']);
    }
}
