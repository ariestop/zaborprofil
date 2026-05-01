<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds `X-Robots-Tag: noindex, nofollow, noarchive` to every response served
 * under `/admin*` so administrative HTML pages and JSON endpoints can never
 * be indexed by search engines, even if a layout block is missed.
 */
final class AdminNoIndexSubscriber implements EventSubscriberInterface
{
    private const string ADMIN_PATH_PREFIX = '/admin';

    private const string ROBOTS_HEADER_NAME = 'X-Robots-Tag';

    private const string ROBOTS_HEADER_VALUE = 'noindex, nofollow, noarchive';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, self::ADMIN_PATH_PREFIX)) {
            return;
        }

        $event->getResponse()->headers->set(self::ROBOTS_HEADER_NAME, self::ROBOTS_HEADER_VALUE);
    }
}
