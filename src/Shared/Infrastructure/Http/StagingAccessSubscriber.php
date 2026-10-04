<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Закрывает staging-стенд (APP_ENV=staging) от посторонних и от поисковых систем.
 *
 * Basic Auth проверяется в приложении, а не в .htaccess/nginx: на Beget директивы Auth* в .htaccess
 * ломают PHP (CGI), а на любом хостинге приложение гарантированно закрыто вместе с кодом.
 * Если проверка включена (STAGING_AUTH_ENABLED=1), но логин или bcrypt-хеш заданы неверно,
 * доступ закрыт для всех: ошибка конфигурации не открывает стенд.
 *
 * Статика, которую веб-сервер отдаёт мимо PHP (build/, uploads/), паролем не закрывается.
 */
final readonly class StagingAccessSubscriber implements EventSubscriberInterface
{
    public const string ENVIRONMENT = 'staging';

    private const string ROBOTS_HEADER_VALUE = 'noindex, nofollow';

    public function __construct(
        private string $environment,
        private bool $authEnabled,
        private string $user,
        private string $passwordHash,
        private string $realm = 'zaborprofil staging',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
            KernelEvents::RESPONSE => ['onKernelResponse', -20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isStagingAuthActive()) {
            return;
        }

        if ($this->isAuthorized($event->getRequest())) {
            return;
        }

        $event->setResponse($this->unauthorizedResponse());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->environment !== self::ENVIRONMENT) {
            return;
        }

        $event->getResponse()->headers->set('X-Robots-Tag', self::ROBOTS_HEADER_VALUE);
    }

    private function isStagingAuthActive(): bool
    {
        return $this->environment === self::ENVIRONMENT && $this->authEnabled;
    }

    private function isAuthorized(Request $request): bool
    {
        $givenUser = $request->getUser();
        $givenPassword = $request->getPassword();

        if ($givenUser === null || $givenPassword === null) {
            return false;
        }

        if ($this->user === '' || !$this->hasValidHash()) {
            return false;
        }

        // Обе проверки выполняются всегда, чтобы время ответа не выдавало верный логин.
        $userMatches = hash_equals($this->user, $givenUser);
        $passwordMatches = password_verify($givenPassword, $this->passwordHash);

        return $userMatches && $passwordMatches;
    }

    private function hasValidHash(): bool
    {
        return $this->passwordHash !== '' && password_get_info($this->passwordHash)['algo'] !== null;
    }

    private function unauthorizedResponse(): Response
    {
        return new Response('401 Unauthorized', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => \sprintf('Basic realm="%s", charset="UTF-8"', $this->realm),
            'Cache-Control' => 'no-store',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
