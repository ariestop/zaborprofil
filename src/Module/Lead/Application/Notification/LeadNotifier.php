<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

use App\Module\Lead\Domain\Entity\Lead;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class LeadNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private string $emailRecipient,
        private string $telegramBotToken,
        private string $telegramChatId,
    ) {
    }

    public function notify(Lead $lead): void
    {
        $payload = $lead->toArray();
        $message = \sprintf(
            "Новая заявка %s\nИсточник: %s\nИмя: %s\nТелефон: %s",
            $this->stringValue($payload, 'id'),
            $this->stringValue($payload, 'source'),
            $this->stringValue($payload, 'name'),
            $this->stringValue($payload, 'phone'),
        );

        if ($this->emailRecipient !== '') {
            $this->mailer->send((new Email())
                ->to($this->emailRecipient)
                ->subject('Новая заявка с сайта zaborprofil.ru')
                ->text($message));
        }

        if ($this->telegramBotToken !== '' && $this->telegramChatId !== '') {
            $this->sendTelegram($message);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return \is_string($value) ? $value : '';
    }

    private function sendTelegram(string $message): void
    {
        $body = json_encode([
            'chat_id' => $this->telegramChatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ], \JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 5,
            ],
        ]);

        @file_get_contents('https://api.telegram.org/bot'.$this->telegramBotToken.'/sendMessage', false, $context);
    }
}
