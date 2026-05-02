<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\MessageHandler;

use App\Module\Settings\Application\Service\SettingsRegistry;
use App\Shared\Infrastructure\Logging\Message\SendTelegramLogMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendTelegramLogMessageHandler
{
    public function __construct(private SettingsRegistry $settings)
    {
    }

    public function __invoke(SendTelegramLogMessage $message): void
    {
        $token = $this->settings->getString('notifications', 'telegram_bot_token', (string) getenv('TELEGRAM_BOT_TOKEN'));
        $chatId = $this->settings->getString('notifications', 'telegram_chat_id', (string) getenv('TELEGRAM_CHAT_ID'));

        if ($token === '' || $chatId === '') {
            return;
        }

        $payload = json_encode([
            'chat_id' => $chatId,
            'text' => $this->telegramText($message),
            'disable_web_page_preview' => true,
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents('https://api.telegram.org/bot'.$token.'/sendMessage', false, $context);
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
