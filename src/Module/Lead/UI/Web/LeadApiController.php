<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Web;

use App\Module\Lead\Application\Submission\PublicLeadSubmitter;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class LeadApiController
{
    public function __construct(
        private PublicLeadSubmitter $submitter,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/api/leads', name: 'api_leads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            $lead = $this->submitter->submit($payload, $request);
            $isSpam = $lead->status() === LeadStatus::SPAM;

            return new JsonResponse(['status' => $isSpam ? 'accepted' : 'created', 'id' => (string) $lead->id()], $isSpam ? 202 : 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Public Lead API');
        }
    }
}
