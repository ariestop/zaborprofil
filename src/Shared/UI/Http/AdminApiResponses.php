<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Единый формат ответов-ошибок Admin API: `{"error": string, "code": string}` (+ `details` у валидации).
 * Ответы без логирования; разбор исключений и запись внутренних ошибок в лог — {@see AdminApiErrorResponder}.
 */
final class AdminApiResponses
{
    public const string CODE_VALIDATION = 'VALIDATION';
    public const string CODE_NOT_FOUND = 'NOT_FOUND';
    public const string CODE_ACCESS_DENIED = 'ACCESS_DENIED';
    public const string CODE_BAD_REQUEST = 'BAD_REQUEST';
    public const string CODE_CONFLICT = 'CONFLICT';
    public const string CODE_INTERNAL = 'INTERNAL';

    private function __construct()
    {
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    public static function validation(string $message, string $code = self::CODE_VALIDATION, array $details = []): JsonResponse
    {
        return self::json($message, $code, 422, $details);
    }

    public static function notFound(string $message = 'Not found.'): JsonResponse
    {
        return self::json($message, self::CODE_NOT_FOUND, 404);
    }

    public static function accessDenied(): JsonResponse
    {
        return self::json('Access denied.', self::CODE_ACCESS_DENIED, 403);
    }

    /**
     * @param array<string, mixed> $extra дополнительные поля ответа (например, список блокирующих объектов)
     */
    public static function conflict(string $message, string $code = self::CODE_CONFLICT, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => $message, 'code' => $code, ...$extra], 409);
    }

    public static function badRequest(string $message): JsonResponse
    {
        return self::json($message, self::CODE_BAD_REQUEST, 400);
    }

    public static function internal(): JsonResponse
    {
        return self::json('Internal server error', self::CODE_INTERNAL, 500);
    }

    /**
     * @param list<array{field: string, message: string}> $details
     */
    private static function json(string $message, string $code, int $status, array $details = []): JsonResponse
    {
        $payload = ['error' => $message, 'code' => $code];
        if ($details !== []) {
            $payload['details'] = $details;
        }

        return new JsonResponse($payload, $status);
    }
}
