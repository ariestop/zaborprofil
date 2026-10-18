<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

use App\Module\Lead\Domain\Entity\Lead;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Уведомления менеджеров о новой заявке: email (`LEAD_NOTIFICATION_EMAIL`) и Telegram (`LEAD_TELEGRAM_*`).
 *
 * В запросе посетителя уведомления только ставятся в очередь ({@see queue()}), отправляет их воркер
 * ({@see NotifyLeadMessageHandler}) с повторами: сбой SMTP или Telegram не теряет уведомление и не замедляет ответ.
 */
final readonly class LeadNotifier
{
    public const string CHANNEL_EMAIL = 'email';
    public const string CHANNEL_TELEGRAM = 'telegram';

    public function __construct(
        private MailerInterface $mailer,
        private TelegramMessageSenderInterface $telegram,
        private MessageBusInterface $bus,
        #[Autowire(service: 'monolog.logger.observability')]
        private LoggerInterface $logger,
        private string $emailRecipient,
        private string $telegramBotToken,
        private string $telegramChatId,
    ) {
    }

    /**
     * Ставит в очередь по сообщению на каждый настроенный канал. Если не настроен ни один — `warning`:
     * заявка сохранена, но менеджер о ней не узнает.
     */
    public function queue(Lead $lead): void
    {
        $leadId = (string) $lead->id();
        $channels = $this->channels();
        if ($channels === []) {
            $this->logger->warning('lead.notification.not_configured', ['event' => 'lead.notification.not_configured', 'leadId' => $leadId]);

            return;
        }

        foreach ($channels as $channel) {
            $this->bus->dispatch(new NotifyLeadMessage($leadId, $channel));
        }
    }

    /**
     * Отправляет уведомление по одному каналу. Сбой — исключение без текста исходной ошибки
     * (в нём бывают адреса и токены): только класс исключения или причина отказа.
     */
    public function send(Lead $lead, string $channel): void
    {
        $text = $this->text($lead);

        if ($channel === self::CHANNEL_EMAIL && $this->emailRecipient !== '') {
            try {
                $this->mailer->send((new Email())
                    ->to($this->emailRecipient)
                    ->subject('Новая заявка с сайта zaborprofil.ru')
                    ->text($text));
            } catch (Throwable $exception) {
                throw new RuntimeException(\sprintf('Lead notification by email failed: %s.', $exception::class));
            }

            return;
        }

        if ($channel === self::CHANNEL_TELEGRAM && $this->telegramBotToken !== '' && $this->telegramChatId !== '') {
            try {
                $sent = $this->telegram->send($this->telegramBotToken, $this->telegramChatId, $text);
            } catch (Throwable $exception) {
                throw new RuntimeException(\sprintf('Lead notification by telegram failed: %s.', $exception::class));
            }
            if (!$sent) {
                throw new RuntimeException('Lead notification by telegram failed: telegram_api_rejected.');
            }

            return;
        }

        // Канал отключили, пока сообщение ждало в очереди.
        $this->logger->warning('lead.notification.channel_disabled', [
            'event' => 'lead.notification.channel_disabled',
            'leadId' => (string) $lead->id(),
            'channel' => $channel,
        ]);
    }

    /**
     * @return list<string>
     */
    private function channels(): array
    {
        $channels = [];
        if ($this->emailRecipient !== '') {
            $channels[] = self::CHANNEL_EMAIL;
        }
        if ($this->telegramBotToken !== '' && $this->telegramChatId !== '') {
            $channels[] = self::CHANNEL_TELEGRAM;
        }

        return $channels;
    }

    private function text(Lead $lead): string
    {
        $payload = $lead->toArray();

        return \sprintf(
            "Новая заявка %s\nИсточник: %s\nИмя: %s\nТелефон: %s",
            $this->stringValue($payload, 'id'),
            $this->stringValue($payload, 'source'),
            $this->stringValue($payload, 'name'),
            $this->stringValue($payload, 'phone'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';

        return \is_string($value) ? $value : '';
    }
}
