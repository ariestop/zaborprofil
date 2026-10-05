<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class CurrentAdminActor
{
    public function __construct(private Security $security)
    {
    }

    public function id(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser ? (string) $user->id() : null;
    }

    public function label(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values($this->security->getUser()?->getRoles() ?? []);
    }
}
