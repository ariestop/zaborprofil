<?php

declare(strict_types=1);

namespace App\Module\Seo\UI\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;

final class AccessDeniedResponse
{
    private function __construct()
    {
    }

    public static function create(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
