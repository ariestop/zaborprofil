<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

final readonly class TelegramMessageSender implements TelegramMessageSenderInterface
{
    private const int TIMEOUT_SECONDS = 5;

    public function send(string $botToken, string $chatId, string $text): bool
    {
        $body = json_encode([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ], \JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
            ],
        ]);

        $response = @file_get_contents('https://api.telegram.org/bot'.$botToken.'/sendMessage', false, $context);
        if ($response === false) {
            return false;
        }

        $decoded = json_decode($response, true);

        return \is_array($decoded) && ($decoded['ok'] ?? false) === true;
    }
}
