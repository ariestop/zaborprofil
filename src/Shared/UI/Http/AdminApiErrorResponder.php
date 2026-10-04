<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Domain\Exception\ClientSafeExceptionInterface;
use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use ValueError;

/**
 * Единый формат ошибок Admin API: `{"error": string, "code": string}`.
 *
 * Тексты доменных ошибок (валидация, «не найдено») попадают в ответ как есть,
 * любые другие исключения заменяются на «Internal server error» и пишутся в лог канала `admin`.
 */
final readonly class AdminApiErrorResponder
{
    public const string CODE_VALIDATION = 'VALIDATION';
    public const string CODE_NOT_FOUND = 'NOT_FOUND';
    public const string CODE_BAD_REQUEST = 'BAD_REQUEST';
    public const string CODE_INTERNAL = 'INTERNAL';

    public function __construct(
        #[Autowire(service: 'monolog.logger.admin')]
        private LoggerInterface $logger,
    ) {
    }

    public function fromThrowable(Throwable $exception, string $operation = 'Admin API'): JsonResponse
    {
        if ($exception instanceof NotFoundExceptionInterface) {
            return $this->notFound($exception->getMessage());
        }

        if (
            $exception instanceof InvalidArgumentException
            || $exception instanceof ValueError
            || $exception instanceof ClientSafeExceptionInterface
        ) {
            return $this->validation($exception->getMessage());
        }

        if ($exception instanceof RequestExceptionInterface || $exception instanceof JsonException) {
            return $this->badRequest('Request body is invalid.');
        }

        return $this->internal($exception, $operation);
    }

    public function validation(string $message, string $code = self::CODE_VALIDATION): JsonResponse
    {
        return $this->json($message, $code, 422);
    }

    public function notFound(string $message = 'Not found.'): JsonResponse
    {
        return $this->json($message, self::CODE_NOT_FOUND, 404);
    }

    public function badRequest(string $message): JsonResponse
    {
        return $this->json($message, self::CODE_BAD_REQUEST, 400);
    }

    public function internal(Throwable $exception, string $operation = 'Admin API'): JsonResponse
    {
        $this->logger->error(\sprintf('%s failed with an unexpected exception.', $operation), [
            'exception' => $exception,
        ]);

        return $this->json('Internal server error', self::CODE_INTERNAL, 500);
    }

    private function json(string $message, string $code, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message, 'code' => $code], $status);
    }
}
