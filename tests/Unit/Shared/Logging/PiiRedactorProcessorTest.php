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

    public function testRedactsInternationalPhonesAndNoteText(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'business',
            level: Level::Info,
            message: 'Callback to +375 29 123-45-67 and +49 (151) 2345 6789 requested',
            context: ['noteText' => 'Клиент просит перезвонить', 'leadId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
            extra: [],
        );

        $processed = (new PiiRedactorProcessor())($record);

        self::assertSame('Callback to [phone] and [phone] requested', $processed->message);
        self::assertSame('[redacted]', $processed->context['noteText']);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $processed->context['leadId']);
    }
}
