<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Service\PageStatusTransitionPolicy;
use App\Module\Content\Domain\ValueObject\PageTransition;
use App\Module\Content\Infrastructure\Security\PageWorkflowVoter;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Единая проверка перехода статуса: права (voter) и допустимость перехода (policy).
 */
final readonly class PageWorkflowGuard
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private PageStatusTransitionPolicy $transitions,
        private CurrentAdminActor $actor,
    ) {
    }

    /**
     * @throws AccessDeniedException   если у администратора нет права на переход
     * @throws InvalidArgumentException если переход не разрешён таблицей статусов
     */
    public function assertAllowed(PageStatus $from, PageStatus $to): void
    {
        if (!$this->authorizationChecker->isGranted(PageWorkflowVoter::TRANSITION, new PageTransition($from, $to))) {
            throw new AccessDeniedException('Access denied.');
        }

        $this->transitions->assertAllowed($from, $to, $this->actor->roles());
    }

    public function canTransition(PageStatus $from, PageStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        if (!$this->authorizationChecker->isGranted(PageWorkflowVoter::TRANSITION, new PageTransition($from, $to))) {
            return false;
        }

        try {
            $this->transitions->assertAllowed($from, $to, $this->actor->roles());
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
