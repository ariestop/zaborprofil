<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Http;

use App\Module\Seo\Application\NotFound\NotFoundRecorder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final readonly class NotFoundLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotFoundRecorder $recorder,
        #[Autowire(service: 'monolog.logger.seo')]
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -64],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || (!$request->isMethod('GET') && !$request->isMethod('HEAD')) || $event->getResponse()->getStatusCode() !== Response::HTTP_NOT_FOUND) {
            return;
        }

        try {
            $this->recorder->record($request->getPathInfo(), $request->headers->get('Referer'));
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to record 404 hit.', ['exception' => $exception]);
        }
    }
}
