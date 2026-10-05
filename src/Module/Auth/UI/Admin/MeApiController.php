<?php

declare(strict_types=1);

namespace App\Module\Auth\UI\Admin;

use App\Module\Auth\Application\Service\AdminAccessProfile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MeApiController
{
    public function __construct(
        private AdminAccessProfile $profile,
    ) {
    }

    #[Route('/admin/api/me', name: 'admin_api_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->profile->current());
    }
}
