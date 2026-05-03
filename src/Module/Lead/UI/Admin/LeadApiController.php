<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Admin;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\UI\Admin\JsonRequest;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Throwable;

#[Route('/admin/api/leads')]
final readonly class LeadApiController
{
    public function __construct(
        private LeadRepositoryInterface $leads,
        private JsonRequest $jsonRequest,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('', name: 'admin_api_leads_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_VIEW)) {
            return $this->accessDenied();
        }

        return new JsonResponse([
            'leads' => array_map(static fn (Lead $lead): array => $lead->toArray(), $this->leads->findLatest()),
            'statuses' => LeadStatus::values(),
        ]);
    }

    #[Route('/{id}/status', name: 'admin_api_leads_status', methods: ['PATCH'])]
    public function status(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(AdminPermission::LEADS_MANAGE)) {
            return $this->accessDenied();
        }

        try {
            $payload = $this->jsonRequest->payload($request);
            $lead = $this->leads->get($id);
            $lead->updateStatus($this->jsonRequest->string($payload, 'status'));
            $this->leads->save($lead);

            return new JsonResponse($lead->toArray());
        } catch (Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 400);
        }
    }

    private function accessDenied(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Access denied.',
            'code' => 'ACCESS_DENIED',
        ], 403);
    }
}
