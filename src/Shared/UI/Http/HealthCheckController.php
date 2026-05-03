<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use App\Shared\Infrastructure\Health\HealthCheckResult;
use App\Shared\Infrastructure\Health\HealthRegistry;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthCheckController
{
    public function __construct(private HealthRegistry $healthRegistry)
    {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $results = $this->healthRegistry->runAll();
        $isHealthy = $this->healthRegistry->isHealthy($results);

        $flatChecks = [];
        foreach ($results as $result) {
            $flatChecks[$result->name] = $result->status;
        }

        return new JsonResponse([
            'status' => $isHealthy ? 'ok' : 'error',
            ...$flatChecks,
            'checks' => array_map(static fn (HealthCheckResult $result): array => $result->toArray(), $results),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], $isHealthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'app' => 'ok',
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $results = $this->healthRegistry->runReadiness();
        $isHealthy = $this->healthRegistry->isHealthy($results);

        return new JsonResponse([
            'status' => $isHealthy ? 'ok' : 'error',
            'checks' => array_map(static fn (HealthCheckResult $result): array => $result->toArray(), $results),
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], $isHealthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}
