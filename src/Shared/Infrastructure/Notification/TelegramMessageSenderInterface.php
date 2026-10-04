<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Notification;

interface TelegramMessageSenderInterface
{
    /**
     * @return bool true, если Telegram Bot API принял сообщение
     */
    public function send(string $botToken, string $chatId, string $text): bool;
}
