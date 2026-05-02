<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AdminApiOriginSubscriber implements EventSubscriberInterface
{
    private const string ADMIN_API_PATH_PREFIX = '/admin/api';

    /**
     * @var list<string>
     */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private string $siteUrl)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), self::ADMIN_API_PATH_PREFIX)) {
            return;
        }

        if (\in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return;
        }

        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!\is_string($origin) || trim($origin) === '') {
            throw new AccessDeniedHttpException('Missing request origin.');
        }

        if (!$this->isAllowedOrigin($origin, $request->getSchemeAndHttpHost())) {
            throw new AccessDeniedHttpException('Invalid request origin.');
        }
    }

    private function isAllowedOrigin(string $origin, string $requestOrigin): bool
    {
        $originParts = parse_url($origin);
        if (!\is_array($originParts)) {
            return false;
        }

        $normalizedOrigin = $this->normalizeOrigin($originParts);
        if ($normalizedOrigin === null) {
            return false;
        }

        return \in_array($normalizedOrigin, array_filter([
            $this->normalizeOriginFromString($requestOrigin),
            $this->normalizeOriginFromString($this->siteUrl),
        ]), true);
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function normalizeOrigin(array $parts): ?string
    {
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (!\is_string($scheme) || !\is_string($host)) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $origin = strtolower($scheme).'://'.strtolower($host);

        if (\is_int($port)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }

    private function normalizeOriginFromString(string $origin): ?string
    {
        $parts = parse_url($origin);

        return \is_array($parts) ? $this->normalizeOrigin($parts) : null;
    }
}
