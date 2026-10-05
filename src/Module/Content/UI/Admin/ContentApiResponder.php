<?php

declare(strict_types=1);

namespace App\Module\Content\UI\Admin;

use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

final readonly class ContentApiResponder
{
    public function __construct(
        private AdminApiErrorResponder $responder,
    ) {
    }

    public function error(Throwable $exception): JsonResponse
    {
        return $this->responder->fromThrowable($exception, 'Admin Content API');
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function conflict(string $message, string $code, array $extra = []): JsonResponse
    {
        return $this->responder->conflict($message, $code, $extra);
    }
}
