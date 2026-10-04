<?php

declare(strict_types=1);

namespace App\Module\Admin\UI\Admin;

use App\Module\Admin\Application\ClientError\ClientErrorReport;
use App\Module\Admin\Application\ClientError\ClientErrorReporter;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use App\Shared\UI\Http\AdminApiErrorResponder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class ClientErrorApiController
{
    public function __construct(
        private ClientErrorReporter $reporter,
        private Security $security,
        #[Autowire(service: 'limiter.admin_client_errors')]
        private RateLimiterFactoryInterface $limiter,
        private AdminApiErrorResponder $errors,
    ) {
    }

    #[Route('/admin/api/client-errors', name: 'admin_api_client_errors', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $user = $this->security->getUser();
            $actorId = $user instanceof AdminUser ? (string) $user->id() : null;

            $limit = $this->limiter->create($actorId ?? $request->getClientIp() ?? 'anonymous')->consume();
            if (!$limit->isAccepted()) {
                return new JsonResponse(['error' => 'Too many client error reports.', 'code' => 'TOO_MANY_REQUESTS'], 429);
            }

            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            $this->reporter->report(ClientErrorReport::fromPayload($payload), $actorId);

            return new JsonResponse(['status' => 'accepted'], 202);
        } catch (Throwable $exception) {
            return $this->errors->fromThrowable($exception, 'Client error report');
        }
    }
}
