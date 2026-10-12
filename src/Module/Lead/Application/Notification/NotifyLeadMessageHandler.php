<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Отправляет уведомление из очереди. Сбой канала пробрасывается: Messenger повторит отправку
 * (`retry_strategy` транспорта `async`), а после последней попытки сообщит {@see \App\Module\Lead\Infrastructure\Messenger\LeadNotificationFailureSubscriber}.
 */
#[AsMessageHandler]
final readonly class NotifyLeadMessageHandler
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private LeadNotifier $notifier,
    ) {
    }

    public function __invoke(NotifyLeadMessage $message): void
    {
        try {
            $lead = $this->leads->get($message->leadId);
        } catch (NotFoundExceptionInterface $exception) {
            // Заявку удалили, пока уведомление ждало в очереди: повторять нечего.
            throw new UnrecoverableMessageHandlingException('Lead was removed before notification.', previous: $exception);
        }

        $this->notifier->send($lead, $message->channel);
    }
}
