<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Logging;

use App\Shared\Infrastructure\Logging\Message\SendTelegramLogMessage;
use App\Shared\Infrastructure\Logging\MessageHandler\SendTelegramLogMessageHandler;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use PHPUnit\Framework\TestCase;

final class SendTelegramLogMessageHandlerTest extends TestCase
{
    public function testSendsAlertWithConfiguredCredentials(): void
    {
        $sender = new RecordingTelegramSender();

        (new SendTelegramLogMessageHandler($sender, 'bot-token', 'chat-1'))(new SendTelegramLogMessage('ERROR', '[ERROR] boom', 'abc'));

        self::assertCount(1, $sender->sent);
        self::assertSame('bot-token', $sender->sent[0]['token']);
        self::assertSame('chat-1', $sender->sent[0]['chat']);
        self::assertStringContainsString('level: ERROR', $sender->sent[0]['text']);
        self::assertStringContainsString('fingerprint: abc', $sender->sent[0]['text']);
        self::assertStringContainsString('[ERROR] boom', $sender->sent[0]['text']);
    }

    public function testSkipsSendingWhenNotConfigured(): void
    {
        $sender = new RecordingTelegramSender();

        (new SendTelegramLogMessageHandler($sender, '', 'chat-1'))(new SendTelegramLogMessage('ERROR', 'boom', 'abc'));
        (new SendTelegramLogMessageHandler($sender, 'bot-token', ''))(new SendTelegramLogMessage('ERROR', 'boom', 'abc'));

        self::assertSame([], $sender->sent);
    }
}

final class RecordingTelegramSender implements TelegramMessageSenderInterface
{
    /**
     * @var list<array{token: string, chat: string, text: string}>
     */
    public array $sent = [];

    public function send(string $botToken, string $chatId, string $text): bool
    {
        $this->sent[] = ['token' => $botToken, 'chat' => $chatId, 'text' => $text];

        return true;
    }
}
