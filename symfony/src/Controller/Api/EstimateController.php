<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Configurator\Application\DTO\ConfigurationDTO;
use App\Estimation\Application\UseCase\CreateEstimateUseCase;
use App\Estimation\Application\UseCase\GetEstimateUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class EstimateController extends AbstractController
{
    public function __construct(
        private readonly CreateEstimateUseCase $createEstimateUseCase,
        private readonly GetEstimateUseCase $getEstimateUseCase,
    ) {
    }

    #[Route('/estimate', name: 'api_estimate_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['config'])) {
            return new JsonResponse(
                ['error' => 'Missing config in request body'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $config = ConfigurationDTO::fromArray($data['config']);
            $guestToken = $data['guestToken'] ?? null;
            $userId = $data['userId'] ?? null;

            $result = $this->createEstimateUseCase->execute($config, $guestToken, $userId);

            return new JsonResponse($result);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    #[Route('/estimate/{id}', name: 'api_estimate_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse|Response
    {
        $estimate = $this->getEstimateUseCase->execute($id);

        if (!$estimate) {
            return new JsonResponse(['error' => 'Estimate not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($estimate);
    }
}
