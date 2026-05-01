<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use ValueError;

final class ContentApiResponder
{
    public function error(Throwable $exception): JsonResponse
    {
        $statusCode = match (true) {
            $exception instanceof ContentNotFoundException => 404,
            $exception instanceof InvalidArgumentException, $exception instanceof ValueError => 422,
            default => 500,
        };

        return new JsonResponse([
            'error' => $exception->getMessage(),
        ], $statusCode);
    }
}
