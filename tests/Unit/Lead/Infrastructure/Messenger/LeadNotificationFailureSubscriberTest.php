<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Infrastructure\Messenger;

use App\Module\Lead\Application\Notification\NotifyLeadMessage;
use App\Module\Lead\Infrastructure\Messenger\LeadNotificationFailureSubscriber;
use App\Tests\Support\Logging\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class LeadNotificationFailureSubscriberTest extends TestCase
{
    public function testFinalFailureIsReportedAsError(): void
    {
        $logger = new RecordingLogger();
        $event = new WorkerMessageFailedEvent(new Envelope(new NotifyLeadMessage('01J0000000000000000000000A', 'telegram')), 'async', new RuntimeException('Lead notification by telegram failed: telegram_api_rejected.'));

        (new LeadNotificationFailureSubscriber($logger))->onMessageFailed($event);

        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('lead.notification.failed', $logger->records[0]['message']);
        self::assertSame('telegram', $logger->records[0]['context']['channel'] ?? null);
        self::assertSame(RuntimeException::class, $logger->records[0]['context']['reason'] ?? null);
    }

    public function testFailureThatWillBeRetriedIsNotAnAlert(): void
    {
        $logger = new RecordingLogger();
        $event = new WorkerMessageFailedEvent(new Envelope(new NotifyLeadMessage('01J0000000000000000000000A', 'email')), 'async', new RuntimeException('SMTP is down'));
        $event->setForRetry();

        (new LeadNotificationFailureSubscriber($logger))->onMessageFailed($event);

        self::assertSame([], $logger->records);
    }

    public function testOtherMessagesAreIgnored(): void
    {
        $logger = new RecordingLogger();

        (new LeadNotificationFailureSubscriber($logger))->onMessageFailed(new WorkerMessageFailedEvent(new Envelope(new stdClass()), 'async', new RuntimeException('x')));

        self::assertSame([], $logger->records);
    }
}
