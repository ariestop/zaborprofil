<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin\ClientError;

use App\Module\Admin\Application\ClientError\ClientErrorReport;
use App\Module\Admin\Application\ClientError\ClientErrorReporter;
use App\Tests\Support\Logging\RecordingLogger;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClientErrorReportTest extends TestCase
{
    public function testNormalizesPayload(): void
    {
        $report = ClientErrorReport::fromPayload([
            'message' => "TypeError:\n  x is undefined",
            'source' => 'error-boundary',
            'url' => 'https://zaborprofil.test/admin/pages/1?token=secret#hash',
            'stack' => str_repeat('a', 5000),
            'componentStack' => '',
        ]);

        self::assertSame('TypeError: x is undefined', $report->message);
        self::assertSame('error-boundary', $report->source);
        self::assertSame('/admin/pages/1', $report->path);
        self::assertSame(4000, mb_strlen((string) $report->stack));
        self::assertNull($report->componentStack);
    }

    public function testRejectsMissingMessageAndUnknownSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClientErrorReport::fromPayload(['message' => '  ']);
    }

    public function testRejectsUnsupportedSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClientErrorReport::fromPayload(['message' => 'boom', 'source' => 'other']);
    }

    public function testRejectsNonStringFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ClientErrorReport::fromPayload(['message' => ['boom']]);
    }

    public function testReporterWritesErrorToObservabilityLogger(): void
    {
        $logger = new RecordingLogger();

        (new ClientErrorReporter($logger))->report(ClientErrorReport::fromPayload(['message' => 'boom']), '42');

        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('admin.client.error: boom', $logger->records[0]['message']);
        self::assertSame('42', $logger->records[0]['context']['actorId'] ?? null);
    }
}
