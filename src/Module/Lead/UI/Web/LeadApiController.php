<?php

declare(strict_types=1);

namespace App\Module\Lead\UI\Web;

use App\Module\Lead\Application\Submission\PublicLeadSubmitter;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Shared\Application\Http\ClientInfo;
use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class LeadApiController
{
    public function __construct(
        private PublicLeadSubmitter $submitter,
        #[Autowire(service: 'limiter.public_leads')]
        private RateLimiterFactoryInterface $limiter,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/api/leads', name: 'api_leads_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            // Считается каждый запрос, в том числе битый и спам: иначе поток запросов заполнил бы БД.
            $limit = $this->limiter->create($request->getClientIp() ?? 'unknown')->consume();
            if (!$limit->isAccepted()) {
                return new JsonResponse(
                    ['error' => 'Too many lead submissions. Try again later.', 'code' => 'TOO_MANY_REQUESTS'],
                    429,
                    ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
                );
            }

            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            $lead = $this->submitter->submit($payload, new ClientInfo($request->getClientIp(), $request->headers->get('User-Agent')));
            $isSpam = $lead->status() === LeadStatus::SPAM;

            return new JsonResponse(['status' => $isSpam ? 'accepted' : 'created', 'id' => (string) $lead->id()], $isSpam ? 202 : 201);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Public Lead API');
        }
    }
}
