<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ВРЕМЕННО, только для ветки диагностики R08: что приложение видит о клиенте за прокси хостинга.
 * Отвечает данными самого запроса; в dev не мержится.
 */
final class TemporaryProxyDiagnosticController
{
    #[Route('/__proxy-diag-40def3d4bf6fe17d94310d20', name: 'temporary_proxy_diagnostic', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $server = [];
        foreach (['REMOTE_ADDR', 'REMOTE_PORT', 'HTTPS', 'SERVER_PORT', 'SERVER_ADDR', 'SERVER_SOFTWARE', 'GATEWAY_INTERFACE', 'HTTP_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_PORT', 'HTTP_FORWARDED', 'HTTP_X_FORWARDED_SSL', 'HTTP_X_HTTPS', 'HTTP_CF_CONNECTING_IP'] as $key) {
            $server[$key] = $request->server->get($key);
        }

        $env = [];
        foreach (['SYMFONY_TRUSTED_PROXIES', 'TRUSTED_PROXIES', 'SYMFONY_TRUSTED_HOSTS', 'TRUSTED_HOSTS', 'SYMFONY_TRUSTED_HEADERS'] as $key) {
            $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
            $env[$key] = $value === false ? null : $value;
        }

        return new JsonResponse([
            'server' => $server,
            'env' => $env,
            'trustedProxies' => Request::getTrustedProxies(),
            'trustedHeaderSet' => Request::getTrustedHeaderSet(),
            'trustedHosts' => Request::getTrustedHosts(),
            'clientIp' => $request->getClientIp(),
            'clientIps' => $request->getClientIps(),
            'isFromTrustedProxy' => $request->isFromTrustedProxy(),
            'isSecure' => $request->isSecure(),
            'schemeAndHost' => $request->getSchemeAndHttpHost(),
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
