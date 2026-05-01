<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;
use ValueError;

final class ContentApiResponder
{
    private readonly LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function error(Throwable $exception): JsonResponse
    {
        if ($exception instanceof ContentNotFoundException) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($exception instanceof InvalidArgumentException || $exception instanceof ValueError) {
            return new JsonResponse([
                'error' => $exception->getMessage(),
                'code' => 'VALIDATION',
            ], 422);
        }

        $this->logger->error('Admin Content API failed with an unexpected exception.', [
            'exception' => $exception,
        ]);

        return new JsonResponse([
            'error' => 'Internal server error',
            'code' => 'INTERNAL',
        ], 500);
    }
}
