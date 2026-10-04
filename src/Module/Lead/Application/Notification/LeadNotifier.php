<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

use App\Module\Lead\Domain\Entity\Lead;
use App\Shared\Infrastructure\Notification\TelegramMessageSenderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Throwable;

final readonly class LeadNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private TelegramMessageSenderInterface $telegram,
        #[Autowire(service: 'monolog.logger.observability')]
        private LoggerInterface $logger,
        private string $emailRecipient,
        private string $telegramBotToken,
        private string $telegramChatId,
    ) {
    }

    /**
     * Не бросает исключений: заявка уже сохранена, сбой уведомления не должен ломать ответ посетителю.
     * Если не удалось доставить уведомление ни по одному каналу, пишется `error` в канал `observability`
     * (уходит в Telegram-алерты и Sentry) — менеджер иначе не узнает о заявке.
     */
    public function notify(Lead $lead): LeadNotificationResult
    {
        $payload = $lead->toArray();
        $message = \sprintf(
            "Новая заявка %s\nИсточник: %s\nИмя: %s\nТелефон: %s",
            $this->stringValue($payload, 'id'),
            $this->stringValue($payload, 'source'),
            $this->stringValue($payload, 'name'),
            $this->stringValue($payload, 'phone'),
        );

        $delivered = [];
        $failed = [];
        $reasons = [];

        if ($this->emailRecipient !== '') {
            try {
                $this->mailer->send((new Email())
                    ->to($this->emailRecipient)
                    ->subject('Новая заявка с сайта zaborprofil.ru')
                    ->text($message));
                $delivered[] = LeadNotificationResult::CHANNEL_EMAIL;
            } catch (Throwable $exception) {
                $failed[] = LeadNotificationResult::CHANNEL_EMAIL;
                $reasons[LeadNotificationResult::CHANNEL_EMAIL] = $exception::class;
            }
        }

        if ($this->telegramBotToken !== '' && $this->telegramChatId !== '') {
            try {
                $sent = $this->telegram->send($this->telegramBotToken, $this->telegramChatId, $message);
            } catch (Throwable $exception) {
                $sent = false;
                $reasons[LeadNotificationResult::CHANNEL_TELEGRAM] = $exception::class;
            }

            if ($sent) {
                $delivered[] = LeadNotificationResult::CHANNEL_TELEGRAM;
            } else {
                $failed[] = LeadNotificationResult::CHANNEL_TELEGRAM;
                $reasons[LeadNotificationResult::CHANNEL_TELEGRAM] ??= 'telegram_api_rejected';
            }
        }

        $result = new LeadNotificationResult($delivered, $failed);
        $this->report($result, $this->stringValue($payload, 'id'), $reasons);

        return $result;
    }

    /**
     * @param array<string, string> $reasons
     */
    private function report(LeadNotificationResult $result, string $leadId, array $reasons): void
    {
        $context = [
            'event' => 'lead.notification.failed',
            'leadId' => $leadId,
            'delivered' => $result->delivered,
            'failed' => $result->failed,
            'reasons' => $reasons,
        ];

        if ($result->isNotConfigured()) {
            $this->logger->warning('lead.notification.not_configured', [...$context, 'event' => 'lead.notification.not_configured']);

            return;
        }

        if (!$result->isDelivered()) {
            $this->logger->error('lead.notification.failed: заявка '.$leadId.' не доставлена ни по одному каналу', $context);

            return;
        }

        if ($result->hasFailures()) {
            $this->logger->warning('lead.notification.partial', [...$context, 'event' => 'lead.notification.partial']);
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
}
