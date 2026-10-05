<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityAuditService
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $user = $this->security->getUser();
        $roles = $user?->getRoles() ?? [];

        return [
            'csrfRequired' => true,
            'originCheckRequired' => true,
            'actor' => [
                'identifier' => $user?->getUserIdentifier(),
                'roles' => $roles,
            ],
            'dangerousActions' => [
                'requiresRole' => 'ROLE_SUPER_ADMIN',
                'requiresConfirmToken' => true,
                'requiresAuditLog' => true,
            ],
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
