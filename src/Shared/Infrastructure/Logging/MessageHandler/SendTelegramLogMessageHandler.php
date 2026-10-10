<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\MessageHandler;

use App\Shared\Infrastructure\Logging\Message\SendTelegramLogMessage;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Отправляет critical-алерт в Telegram. Токен и чат приходят из `TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID`
 * через контейнер: так работают и `.env.local`, и `secrets:set` (через `getenv()` они не видны).
 */
#[AsMessageHandler]
final readonly class SendTelegramLogMessageHandler
{
    public function __construct(
        private TelegramMessageSenderInterface $telegram,
        #[Autowire('%env(default:empty_string:TELEGRAM_BOT_TOKEN)%')]
        private string $botToken,
        #[Autowire('%env(default:empty_string:TELEGRAM_CHAT_ID)%')]
        private string $chatId,
    ) {
    }

    public function __invoke(SendTelegramLogMessage $message): void
    {
        if ($this->botToken === '' || $this->chatId === '') {
            return;
        }

        $this->telegram->send($this->botToken, $this->chatId, $this->telegramText($message));
    }

    private function telegramText(SendTelegramLogMessage $message): string
    {
        return implode("\n", [
            'Zaborprofil CMS alert',
            'level: '.$message->level,
            'fingerprint: '.$message->fingerprint,
            '',
            $message->message,
        ]);
    }
}
