<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Maintenance;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final readonly class MaintenanceSubscriber implements EventSubscriberInterface
{
    private const array ALLOWED_PREFIXES = [
        '/admin',
        '/build',
        '/health',
        '/uploads',
        '/_profiler',
        '/_wdt',
    ];

    public function __construct(
        private MaintenanceState $maintenance,
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 192],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->maintenance->isEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        if ($this->maintenance->isIpAllowed($request->getClientIp())) {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('maintenance.html.twig', [
                'message' => $this->maintenance->message(),
            ]),
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Retry-After' => '600',
                'X-Robots-Tag' => 'noindex, nofollow',
            ],
        ));
    }
}
