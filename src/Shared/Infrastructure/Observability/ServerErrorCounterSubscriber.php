<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Observability;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ServerErrorCounterSubscriber implements EventSubscriberInterface
{
    public function __construct(private ServerErrorCounter $counter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -512],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $event->getResponse()->getStatusCode() < 500) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ($path === '/health' || str_starts_with($path, '/health/')) {
            return;
        }

        try {
            $this->counter->increment();
        } catch (\Throwable) {
            // Счётчик не должен ломать ответ клиенту.
        }
    }
}
