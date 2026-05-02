<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Logging;

use App\Shared\Infrastructure\Logging\Message\SendTelegramLogMessage;
use App\Shared\Infrastructure\Logging\TelegramErrorHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class TelegramErrorHandlerTest extends TestCase
{
    public function testDispatchesOnlyOneMessagePerFingerprintWithinThrottleWindow(): void
    {
        $messageBus = new CollectingMessageBus();
        $handler = new TelegramErrorHandler($messageBus, new ArrayAdapter());
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Error,
            message: 'Database failed',
            context: [],
            extra: ['request_id' => 'req-1'],
        );

        $handler->handle($record);
        $handler->handle($record);

        self::assertCount(1, $messageBus->messages);
        self::assertInstanceOf(SendTelegramLogMessage::class, $messageBus->messages[0]);
    }
}

final class CollectingMessageBus implements MessageBusInterface
{
    /**
     * @var list<object>
     */
    public array $messages = [];

    /**
     * @param list<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
