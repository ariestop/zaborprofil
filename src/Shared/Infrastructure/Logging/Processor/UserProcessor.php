<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Processor;

use Monolog\LogRecord;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserProcessor
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || !str_starts_with($request->getPathInfo(), '/admin')) {
            return $record;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return $record;
        }

        $record->extra['user_id'] = $user->getUserIdentifier();
        $record->extra['roles'] = $user->getRoles();

        return $record;
    }
}
