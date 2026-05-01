<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Enforces a double-submit CSRF token on every state-changing request to
 * `/admin/api/*`. The Vue admin reads the token from a meta tag rendered by
 * the server-side admin layout and sends it back in the `X-CSRF-Token`
 * header. GET/HEAD/OPTIONS requests are allowed without a token because they
 * are expected to be safe.
 */
final class AdminApiCsrfSubscriber implements EventSubscriberInterface
{
    public const string TOKEN_ID = 'admin_api';

    public const string HEADER_NAME = 'X-CSRF-Token';

    private const string ADMIN_API_PATH_PREFIX = '/admin/api';

    /**
     * @var list<string>
     */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
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

        $token = $request->headers->get(self::HEADER_NAME, '');
        if ('' === $token || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }
}
