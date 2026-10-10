<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Shared\UI\Http\AdminApiResponses;
use App\Shared\UI\Http\JsonRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

trait SystemControllerTrait
{
    protected function validationError(string $message): JsonResponse
    {
        return AdminApiResponses::validation($message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonPayload(Request $request): array
    {
        return new JsonRequest()->lenientPayload($request);
    }
}
