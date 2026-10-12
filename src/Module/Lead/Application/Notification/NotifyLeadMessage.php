<?php

declare(strict_types=1);

namespace App\Module\Lead\Application\Notification;

/**
 * Уведомить менеджеров о заявке по одному каналу. Каналы — отдельными сообщениями: каждый повторяется
 * независимо, и сбой Telegram не приводит к повторному письму.
 */
final readonly class NotifyLeadMessage
{
    public function __construct(
        public string $leadId,
        public string $channel,
    ) {
    }
}
