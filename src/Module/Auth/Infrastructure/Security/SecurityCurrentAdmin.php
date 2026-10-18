<?php

declare(strict_types=1);

namespace App\Module\Auth\Infrastructure\Security;

use App\Module\Auth\Application\Security\CurrentAdminInterface;
use App\Module\User\Domain\Entity\AdminUser;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SecurityCurrentAdmin implements CurrentAdminInterface
{
    public function __construct(private Security $security)
    {
    }

    public function id(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }

    public function identifier(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function roles(): array
    {
        return array_values($this->security->getUser()?->getRoles() ?? []);
    }
}
