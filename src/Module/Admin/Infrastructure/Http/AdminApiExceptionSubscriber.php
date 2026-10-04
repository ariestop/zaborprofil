<?php

declare(strict_types=1);

namespace App\Module\Admin\Infrastructure\Http;

use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Последний рубеж для `/admin/api/*`: необработанные исключения превращаются в JSON
 * `{"error", "code"}` без текста исключения (SQL, пути, внутренние детали), а подробности пишутся в лог `admin`.
 */
final readonly class AdminApiExceptionSubscriber implements EventSubscriberInterface
{
    private const string ADMIN_API_PATH_PREFIX = '/admin/api';

    /**
     * @var array<int, string>
     */
    private const array HTTP_CODES = [
        400 => 'BAD_REQUEST',
        401 => 'UNAUTHORIZED',
        403 => 'ACCESS_DENIED',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
        409 => 'CONFLICT',
        413 => 'PAYLOAD_TOO_LARGE',
        415 => 'UNSUPPORTED_MEDIA_TYPE',
        422 => 'VALIDATION',
        429 => 'TOO_MANY_REQUESTS',
    ];

    public function __construct(
        private AdminApiErrorResponder $responder,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || $event->hasResponse()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ($path !== self::ADMIN_API_PATH_PREFIX && !str_starts_with($path, self::ADMIN_API_PATH_PREFIX.'/')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $event->setResponse($this->httpResponse($exception));

            return;
        }

        $event->setResponse($this->responder->fromThrowable($exception, 'Admin API'));
    }

    /**
     * Сообщения 4xx-исключений приложения (CSRF, Origin и т.п.) написаны намеренно; маршрутизатор и 5xx получают нейтральный текст.
     */
    private function httpResponse(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $message = match (true) {
            $status >= 500 => 'Internal server error',
            $status === 404 || $status === 405 => Response::$statusTexts[$status],
            $status >= 400 && $exception->getMessage() !== '' => $exception->getMessage(),
            default => Response::$statusTexts[$status] ?? 'Request failed.',
        };
        $code = $status >= 500 ? AdminApiErrorResponder::CODE_INTERNAL : (self::HTTP_CODES[$status] ?? 'HTTP_ERROR');

        return new JsonResponse(['error' => $message, 'code' => $code], $status, $exception->getHeaders());
    }
}
