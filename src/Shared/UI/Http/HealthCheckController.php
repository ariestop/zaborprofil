<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthCheckController
{
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'zaborprofil-engine',
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
