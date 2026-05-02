<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Ulid;

final class RequestIdSubscriber implements EventSubscriberInterface
{
    public const string ATTRIBUTE = 'request_id';
    public const string HEADER = 'X-Request-Id';
    public const string START_TIME_ATTRIBUTE = 'request_started_at';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $requestId = $this->normalizeRequestId($request->headers->get(self::HEADER));

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        $request->attributes->set(self::START_TIME_ATTRIBUTE, microtime(true));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (\is_string($requestId) && $requestId !== '') {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }

    private function normalizeRequestId(?string $requestId): string
    {
        $normalized = $requestId === null ? '' : trim($requestId);

        if ($normalized !== '' && preg_match('/^[a-zA-Z0-9_.:-]{8,128}$/', $normalized)) {
            return $normalized;
        }

        return (string) new Ulid();
    }
}
