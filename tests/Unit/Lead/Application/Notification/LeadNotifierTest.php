<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Application\Notification;

use App\Module\Lead\Application\Notification\LeadNotifier;
use App\Module\Lead\Domain\Entity\Lead;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use App\Tests\Support\Logging\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class LeadNotifierTest extends TestCase
{
    public function testDeliversByEmailAndTelegramWithoutAlerts(): void
    {
        $logger = new RecordingLogger();
        $mailer = new RecordingMailer();
        $telegram = new FakeTelegramSender(true);

        $result = $this->notifier($mailer, $telegram, $logger, 'sales@example.test', 'token', 'chat')->notify($this->lead());

        self::assertSame(['email', 'telegram'], $result->delivered);
        self::assertSame([], $result->failed);
        self::assertSame(1, $mailer->sent);
        self::assertSame(['chat'], $telegram->chatIds);
        self::assertSame([], $logger->records);
    }

    public function testLogsErrorWhenNoChannelDeliveredTheLead(): void
    {
        $logger = new RecordingLogger();

        $result = $this->notifier(new RecordingMailer(true), new FakeTelegramSender(false), $logger, 'sales@example.test', 'token', 'chat')->notify($this->lead());

        self::assertFalse($result->isDelivered());
        self::assertSame(['email', 'telegram'], $result->failed);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringContainsString('lead.notification.failed', $logger->records[0]['message']);
        self::assertSame(['email', 'telegram'], $logger->records[0]['context']['failed'] ?? null);
        self::assertSame(
            ['email' => TransportException::class, 'telegram' => 'telegram_api_rejected'],
            $logger->records[0]['context']['reasons'] ?? null,
        );
    }

    public function testTelegramExceptionIsReportedWithoutLeakingMessage(): void
    {
        $logger = new RecordingLogger();

        $result = $this->notifier(new RecordingMailer(), new FakeTelegramSender(false, true), $logger, '', 'secret-token', 'chat')->notify($this->lead());

        self::assertSame(['telegram'], $result->failed);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertStringNotContainsString('secret-token', json_encode($logger->records, \JSON_THROW_ON_ERROR));
        self::assertSame(['telegram' => \RuntimeException::class], $logger->records[0]['context']['reasons'] ?? null);
    }

    public function testPartialFailureIsOnlyAWarning(): void
    {
        $logger = new RecordingLogger();

        $result = $this->notifier(new RecordingMailer(true), new FakeTelegramSender(true), $logger, 'sales@example.test', 'token', 'chat')->notify($this->lead());

        self::assertTrue($result->isDelivered());
        self::assertTrue($result->hasFailures());
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
    }

    public function testNotConfiguredIsOnlyAWarning(): void
    {
        $logger = new RecordingLogger();

        $result = $this->notifier(new RecordingMailer(), new FakeTelegramSender(true), $logger, '', '', '')->notify($this->lead());

        self::assertTrue($result->isNotConfigured());
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('lead.notification.not_configured', $logger->records[0]['message']);
    }

    private function notifier(MailerInterface $mailer, TelegramMessageSenderInterface $telegram, RecordingLogger $logger, string $email, string $token, string $chatId): LeadNotifier
    {
        return new LeadNotifier($mailer, $telegram, $logger, $email, $token, $chatId);
    }

    private function lead(): Lead
    {
        return new Lead('public_form', 'Иван', '+79990000000', null, null, ['consent' => true]);
    }
}

final class RecordingMailer implements MailerInterface
{
    public int $sent = 0;

    public function __construct(private readonly bool $fail = false)
    {
    }

    public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
    {
        if ($this->fail) {
            throw new TransportException('SMTP is down for user@example.test');
        }

        ++$this->sent;
    }
}

final class FakeTelegramSender implements TelegramMessageSenderInterface
{
    /**
     * @var list<string>
     */
    public array $chatIds = [];

    public function __construct(private readonly bool $accepts, private readonly bool $throws = false)
    {
    }

    public function send(string $botToken, string $chatId, string $text): bool
    {
        $this->chatIds[] = $chatId;
        if ($this->throws) {
            throw new \RuntimeException('Connection to bot'.$botToken.' failed');
        }

        return $this->accepts;
    }
}
