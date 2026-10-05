<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait SystemControllerTrait
{
    protected function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }

    protected function validationError(string $message): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
            'code' => 'VALIDATION_ERROR',
        ], 422);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonPayload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);
        if (!\is_array($decoded)) {
            return [];
        }

        $payload = [];
        foreach ($decoded as $key => $value) {
            if (\is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
