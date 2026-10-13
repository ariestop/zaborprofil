<?php

declare(strict_types=1);

namespace App\Module\Admin\Application\Service;

use App\Module\Auth\Application\Security\CurrentAdminInterface;
use DateTimeImmutable;

final readonly class SecurityAuditService
{
    public function __construct(
        private CurrentAdminInterface $currentAdmin,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {

        return [
            'csrfRequired' => true,
            'originCheckRequired' => true,
            'actor' => [
                'identifier' => $this->currentAdmin->identifier(),
                'roles' => $this->currentAdmin->roles(),
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
