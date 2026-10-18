<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Messenger;

use App\Module\Lead\Application\Notification\NotifyLeadMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Уведомление о заявке не доставлено после всех повторов: `error` в канал `observability`
 * (уходит в Telegram-алерты и Sentry) — иначе менеджер не узнает о заявке. Сообщение остаётся
 * в транспорте `failed`, его можно отправить заново: `messenger:failed:retry`.
 */
final readonly class LeadNotificationFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.observability')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkerMessageFailedEvent::class => 'onMessageFailed'];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof NotifyLeadMessage || $event->willRetry()) {
            return;
        }

        $this->logger->error(\sprintf('lead.notification.failed: заявка %s не доставлена в канал %s', $message->leadId, $message->channel), [
            'event' => 'lead.notification.failed',
            'leadId' => $message->leadId,
            'channel' => $message->channel,
            'reason' => $event->getThrowable()::class,
        ]);
    }
}
