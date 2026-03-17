<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Leads\Infrastructure\Persistence\Entity\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
final class LeadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/lead', name: 'api_lead_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $lead = new Lead();
        $lead->setName($data['name'] ?? '');
        $lead->setPhone($data['phone'] ?? '');
        $lead->setEmail($data['email'] ?? '');
        $lead->setMessage($data['message'] ?? null);
        $lead->setSource($data['source'] ?? 'website');

        $errors = $this->validator->validate($lead);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $messages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->entityManager->persist($lead);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $lead->getId(),
            'message' => 'Lead received',
        ], Response::HTTP_CREATED);
    }
}
