<?php

declare(strict_types=1);

namespace App\Module\Auth\Infrastructure\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Adds focused diagnostics for failed admin logins so operators can quickly
 * distinguish CSRF/origin issues from credential or rate-limit problems.
 */
final readonly class AdminLoginFailureDiagnosticsSubscriber implements EventSubscriberInterface
{
    private const string ADMIN_LOGIN_ROUTE = 'admin_login';

    public function __construct(
        #[Autowire(service: 'monolog.logger.security')]
        private LoggerInterface $securityLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->getString('_route') !== self::ADMIN_LOGIN_ROUTE) {
            return;
        }

        $exception = $event->getException();
        $context = [
            'failure_type' => $exception::class,
            'failure_message' => $exception->getMessageKey(),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->getClientIp(),
            'origin' => $request->headers->get('Origin'),
            'referer' => $request->headers->get('Referer'),
            'host' => $request->headers->get('Host'),
            'x_forwarded_host' => $request->headers->get('X-Forwarded-Host'),
            'x_forwarded_proto' => $request->headers->get('X-Forwarded-Proto'),
            'content_type' => $request->headers->get('Content-Type'),
        ];

        if ($exception instanceof InvalidCsrfTokenException) {
            $this->securityLogger->warning(
                'Admin login rejected: invalid CSRF token. Check same-origin headers and proxy forwarding.',
                $context,
            );

            return;
        }

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $this->securityLogger->notice(
                'Admin login rejected: login throttling triggered.',
                $context,
            );
        }
    }
}
