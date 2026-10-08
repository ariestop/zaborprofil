<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Application\Exception\ClientErrorClassifier;
use App\Shared\Domain\Exception\NotFoundExceptionInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;

/**
 * Единый формат ошибок Admin API: `{"error": string, "code": string}`.
 *
 * Тексты доменных ошибок (валидация, «не найдено») попадают в ответ как есть,
 * любые другие исключения заменяются на «Internal server error» и пишутся в лог канала `admin`.
 * Что считать ошибкой валидации, решает {@see ClientErrorClassifier}.
 */
final readonly class AdminApiErrorResponder
{
    public const string CODE_VALIDATION = AdminApiResponses::CODE_VALIDATION;
    public const string CODE_NOT_FOUND = AdminApiResponses::CODE_NOT_FOUND;
    public const string CODE_ACCESS_DENIED = AdminApiResponses::CODE_ACCESS_DENIED;
    public const string CODE_BAD_REQUEST = AdminApiResponses::CODE_BAD_REQUEST;
    public const string CODE_CONFLICT = AdminApiResponses::CODE_CONFLICT;
    public const string CODE_INTERNAL = AdminApiResponses::CODE_INTERNAL;

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

        if ($exception instanceof AccessDeniedException) {
            return $this->accessDenied();
        }

        if (ClientErrorClassifier::isValidationError($exception)) {
            return $this->validation($exception->getMessage());
        }

        if ($exception instanceof RequestExceptionInterface || $exception instanceof JsonException) {
            return $this->badRequest('Request body is invalid.');
        }

        return $this->internal($exception, $operation);
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    public function validation(string $message, string $code = self::CODE_VALIDATION, array $details = []): JsonResponse
    {
        return AdminApiResponses::validation($message, $code, $details);
    }

    public function notFound(string $message = 'Not found.'): JsonResponse
    {
        return AdminApiResponses::notFound($message);
    }

    public function accessDenied(): JsonResponse
    {
        return AdminApiResponses::accessDenied();
    }

    /**
     * @param array<string, mixed> $extra дополнительные поля ответа (например, список блокирующих объектов)
     */
    public function conflict(string $message, string $code = self::CODE_CONFLICT, array $extra = []): JsonResponse
    {
        return AdminApiResponses::conflict($message, $code, $extra);
    }

    public function badRequest(string $message): JsonResponse
    {
        return AdminApiResponses::badRequest($message);
    }

    public function internal(Throwable $exception, string $operation = 'Admin API'): JsonResponse
    {
        $this->logger->error(\sprintf('%s failed with an unexpected exception.', $operation), [
            'exception' => $exception,
        ]);

        return AdminApiResponses::internal();
    }
}
