<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Security;

use App\Module\Lead\Domain\ValueObject\LeadActor;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class LeadActorProvider
{
    public function __construct(private Security $security)
    {
    }

    public function current(): LeadActor
    {
        $user = $this->security->getUser();

        return $user instanceof AdminUser
            ? new LeadActor((string) $user->id(), $user->email())
            : LeadActor::system();
    }
}
