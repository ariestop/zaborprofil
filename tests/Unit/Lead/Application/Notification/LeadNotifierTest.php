<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lead\Application\Notification;

use App\Module\Lead\Application\Notification\LeadNotifier;
use App\Module\Lead\Application\Notification\NotifyLeadMessage;
use App\Module\Lead\Domain\Entity\Lead;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use App\Tests\Support\Logging\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\RawMessage;

final class LeadNotifierTest extends TestCase
{
    public function testQueuesOneMessagePerConfiguredChannel(): void
    {
        $bus = new RecordingBus();
        $lead = $this->lead();

        $this->notifier(new RecordingMailer(), new FakeTelegramSender(true), new RecordingLogger(), 'sales@example.test', 'token', 'chat', $bus)->queue($lead);

        self::assertEquals([
            new NotifyLeadMessage((string) $lead->id(), LeadNotifier::CHANNEL_EMAIL),
            new NotifyLeadMessage((string) $lead->id(), LeadNotifier::CHANNEL_TELEGRAM),
        ], $bus->messages);
    }

    public function testQueuesOnlyTelegramWhenEmailIsNotConfigured(): void
    {
        $bus = new RecordingBus();

        $this->notifier(new RecordingMailer(), new FakeTelegramSender(true), new RecordingLogger(), '', 'token', 'chat', $bus)->queue($this->lead());

        self::assertCount(1, $bus->messages);
        self::assertSame(LeadNotifier::CHANNEL_TELEGRAM, $bus->messages[0]->channel);
    }

    public function testNotConfiguredIsOnlyAWarningAndQueuesNothing(): void
    {
        $logger = new RecordingLogger();
        $bus = new RecordingBus();

        $this->notifier(new RecordingMailer(), new FakeTelegramSender(true), $logger, '', '', '', $bus)->queue($this->lead());

        self::assertSame([], $bus->messages);
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('lead.notification.not_configured', $logger->records[0]['message']);
    }

    public function testSendsByEmailAndTelegramWithoutAlerts(): void
    {
        $logger = new RecordingLogger();
        $mailer = new RecordingMailer();
        $telegram = new FakeTelegramSender(true);
        $notifier = $this->notifier($mailer, $telegram, $logger, 'sales@example.test', 'token', 'chat');

        $notifier->send($this->lead(), LeadNotifier::CHANNEL_EMAIL);
        $notifier->send($this->lead(), LeadNotifier::CHANNEL_TELEGRAM);

        self::assertSame(1, $mailer->sent);
        self::assertSame(['chat'], $telegram->chatIds);
        self::assertSame([], $logger->records);
    }

    public function testEmailFailureIsThrownForRetryWithoutLeakingAddress(): void
    {
        $notifier = $this->notifier(new RecordingMailer(true), new FakeTelegramSender(true), new RecordingLogger(), 'sales@example.test', 'token', 'chat');

        try {
            $notifier->send($this->lead(), LeadNotifier::CHANNEL_EMAIL);
            self::fail('Email failure must be thrown so that Messenger retries it.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Lead notification by email failed: '.TransportException::class.'.', $exception->getMessage());
        }
    }

    public function testTelegramRejectionIsThrownForRetry(): void
    {
        $this->expectExceptionMessage('Lead notification by telegram failed: telegram_api_rejected.');

        $this->notifier(new RecordingMailer(), new FakeTelegramSender(false), new RecordingLogger(), '', 'token', 'chat')->send($this->lead(), LeadNotifier::CHANNEL_TELEGRAM);
    }

    public function testTelegramExceptionIsThrownWithoutLeakingToken(): void
    {
        try {
            $this->notifier(new RecordingMailer(), new FakeTelegramSender(false, true), new RecordingLogger(), '', 'secret-token', 'chat')->send($this->lead(), LeadNotifier::CHANNEL_TELEGRAM);
            self::fail('Telegram failure must be thrown so that Messenger retries it.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Lead notification by telegram failed: RuntimeException.', $exception->getMessage());
            self::assertStringNotContainsString('secret-token', $exception->getMessage());
        }
    }

    public function testChannelDisabledWhileQueuedIsSkippedWithWarning(): void
    {
        $logger = new RecordingLogger();
        $mailer = new RecordingMailer();

        $this->notifier($mailer, new FakeTelegramSender(true), $logger, '', '', '')->send($this->lead(), LeadNotifier::CHANNEL_EMAIL);

        self::assertSame(0, $mailer->sent);
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('lead.notification.channel_disabled', $logger->records[0]['message']);
    }

    private function notifier(MailerInterface $mailer, TelegramMessageSenderInterface $telegram, RecordingLogger $logger, string $email, string $token, string $chatId, ?RecordingBus $bus = null): LeadNotifier
    {
        return new LeadNotifier($mailer, $telegram, $bus ?? new RecordingBus(), $logger, $email, $token, $chatId);
    }

    private function lead(): Lead
    {
        return new Lead('public_form', 'Иван', '+79990000000', null, null, ['consent' => true]);
    }
}

final class RecordingBus implements MessageBusInterface
{
    /**
     * @var list<NotifyLeadMessage>
     */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if ($message instanceof NotifyLeadMessage) {
            $this->messages[] = $message;
        }

        return new Envelope($message, $stamps);
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
