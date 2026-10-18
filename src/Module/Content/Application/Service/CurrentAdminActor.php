<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Auth\Application\Security\CurrentAdminInterface;

final readonly class CurrentAdminActor
{
    public function __construct(private CurrentAdminInterface $currentAdmin)
    {
    }

    public function id(): ?string
    {
        return $this->currentAdmin->id();
    }

    public function label(): ?string
    {
        return $this->currentAdmin->identifier();
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->currentAdmin->roles();
    }
}
