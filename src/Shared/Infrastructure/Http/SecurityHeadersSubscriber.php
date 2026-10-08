<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Заголовки безопасности для всех ответов приложения.
 *
 * Content-Security-Policy включается в режиме enforce параметром `CONTENT_SECURITY_POLICY_ENFORCED`
 * (prod, staging и test — да, dev — нет: там работают Vite dev-сервер и панель профайлера с inline-скриптами,
 * поэтому та же политика отправляется как Report-Only). Скрипты разрешены только с собственного origin:
 * Vite собирает их в файлы `/build/`, inline-скриптов в шаблонах нет (JSON-LD — данные, а не исполняемый код).
 * Inline-стили остаются разрешены: выравнивание в rich-text хранится в атрибуте `style`, а библиотеки
 * админки вставляют `<style>`; риск CSS-инъекций ниже, чем у скриптов. Подробнее — docs/20-security-and-access-control.md.
 */
final readonly class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private ?string $devServerOrigin;

    public function __construct(
        private string $environment,
        private bool $enforceContentSecurityPolicy = false,
        string $viteDevServerUrl = '',
    ) {
        $this->devServerOrigin = self::origin($viteDevServerUrl);
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
            // С Vite dev-сервером скрипты приходят с другого origin и есть inline-преамбула React Refresh.
            $enforce = $this->enforceContentSecurityPolicy && $this->devServerOrigin === null;
            $headers->set($enforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $this->contentSecurityPolicy());
        }

        if ($this->environment === 'prod' && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSrc = ["'self'"];
        $connectSrc = ["'self'"];
        $styleSrc = ["'self'", "'unsafe-inline'"];
        if ($this->devServerOrigin !== null) {
            $scriptSrc = [...$scriptSrc, $this->devServerOrigin, "'unsafe-inline'"];
            $styleSrc[] = $this->devServerOrigin;
            $connectSrc = [...$connectSrc, $this->devServerOrigin, (string) preg_replace('#^http#', 'ws', $this->devServerOrigin)];
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https:",
            "media-src 'self' https:",
            // Видео (Rutube, VK Видео) подключаются по нажатию, карта — 2ГИС.
            "frame-src 'self' https://rutube.ru https://vkvideo.ru https://vk.com https://*.2gis.ru https://*.2gis.com",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            "font-src 'self'",
            'connect-src '.implode(' ', $connectSrc),
        ]);
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
