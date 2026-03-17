<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Configurator\Application\DTO\ConfigurationDTO;
use App\Configurator\Domain\Service\CalculationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class CalculateController extends AbstractController
{
    public function __construct(
        private readonly CalculationEngine $calculationEngine,
    ) {
    }

    #[Route('/calculate', name: 'api_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
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
            $priceProfileId = isset($data['priceProfileId']) ? (int) $data['priceProfileId'] : null;

            $result = $this->calculationEngine->calculate($config, $priceProfileId);

            return new JsonResponse($result->toArray());
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
