<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ChatController extends AbstractController
{
    #[Route('/chat', name: 'api_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['message'])) {
            return new JsonResponse(
                ['error' => 'Missing message in request body'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $message = $data['message'];
        $sessionId = $data['sessionId'] ?? null;
        $context = $data['context'] ?? [];

        $response = $this->generateResponse($message, $context);

        return new JsonResponse([
            'response' => $response,
            'sessionId' => $sessionId,
        ]);
    }

    private function generateResponse(string $message, array $context): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'калькулятор') || str_contains($lower, 'calculator')) {
            return 'Калькулятор позволяет рассчитать стоимость забора. Выберите продукт, укажите размеры и количество. Нажмите "Рассчитать" для получения сметы.';
        }

        if (str_contains($lower, 'цена') || str_contains($lower, 'стоимость') || str_contains($lower, 'price')) {
            return 'Цены рассчитываются индивидуально на основе выбранной конфигурации. Используйте калькулятор на странице продукта для расчёта.';
        }

        if (str_contains($lower, 'связаться') || str_contains($lower, 'контакт') || str_contains($lower, 'contact')) {
            return 'Оставьте заявку через форму на сайте, и наш менеджер свяжется с вами в ближайшее время.';
        }

        return 'Здравствуйте! Я могу помочь с информацией о продуктах, калькуляторе и оформлении заявки. Задайте вопрос.';
    }
}
