<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Estimation\Application\UseCase\GetEstimateUseCase;
use App\Orders\Infrastructure\Persistence\Entity\Order;
use App\Orders\Infrastructure\Persistence\Entity\OrderItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly GetEstimateUseCase $getEstimateUseCase,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/order', name: 'api_order_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['estimateId'])) {
            return new JsonResponse(
                ['error' => 'Missing estimateId in request body'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $estimate = $this->getEstimateUseCase->execute((int) $data['estimateId']);
        if (!$estimate) {
            return new JsonResponse(['error' => 'Estimate not found'], Response::HTTP_NOT_FOUND);
        }

        $order = new Order();
        $order->setEstimateId((int) $data['estimateId']);
        $order->setTotalPrice((string) $estimate['totalPrice']);
        $order->setStatus('pending');
        $order->setContactName($data['contact']['name'] ?? null);
        $order->setContactEmail($data['contact']['email'] ?? null);
        $order->setContactPhone($data['contact']['phone'] ?? null);

        $this->entityManager->persist($order);

        foreach ($estimate['lines'] as $line) {
            $orderItem = new OrderItem();
            $orderItem->setOrder($order);
            $orderItem->setProductId(0);
            $orderItem->setQuantity($line['quantity']);
            $orderItem->setPrice((string) $line['price']);
            $this->entityManager->persist($orderItem);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'orderId' => $order->getId(),
            'status' => $order->getStatus(),
        ]);
    }
}
