<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $environment)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (!$headers->has('Content-Security-Policy') && !$headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());
        }

        if ($this->environment === 'prod' && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: https:",
            "media-src 'self' https:",
            // Видео (Rutube, VK Видео) подключаются по нажатию, карта — 2ГИС.
            "frame-src 'self' https://rutube.ru https://vkvideo.ru https://vk.com https://*.2gis.ru https://*.2gis.com",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "connect-src 'self'",
        ]);
    }
}
