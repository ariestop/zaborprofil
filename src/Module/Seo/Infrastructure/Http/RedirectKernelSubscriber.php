<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Http;

use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RedirectKernelSubscriber implements EventSubscriberInterface
{
    private const array IGNORED_PREFIXES = [
        '/admin',
        '/build',
        '/health',
        '/uploads',
        '/_profiler',
        '/_wdt',
    ];

    public function __construct(private RedirectRepositoryInterface $redirects)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->isMethod('GET')) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $redirect = $this->redirects->findActiveBySourcePath($path);
        if ($redirect === null || $redirect->targetPath() === $path) {
            return;
        }

        $redirect->registerHit();
        $this->redirects->save($redirect);

        $event->setResponse(new RedirectResponse($redirect->targetPath(), $redirect->statusCode()));
    }
}
