<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Security;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\ValueObject\PageTransition;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Решает, может ли текущий администратор выполнить конкретный переход статуса страницы.
 * Сами права остаются в {@see AdminPermission}; голосующий лишь сопоставляет переход с нужным правом.
 *
 * @extends Voter<string, PageTransition>
 */
final class PageWorkflowVoter extends Voter
{
    public const string TRANSITION = 'page.workflow.transition';

    public function __construct(private readonly AccessDecisionManagerInterface $decisionManager)
    {
    }

    public static function permissionFor(PageTransition $transition): string
    {
        return match ($transition->to) {
            PageStatus::Review => AdminPermission::PAGES_SUBMIT_REVIEW,
            PageStatus::Approved => $transition->from === PageStatus::Scheduled
                ? AdminPermission::PAGES_SCHEDULE
                : AdminPermission::PAGES_APPROVE,
            PageStatus::Published => AdminPermission::PAGES_PUBLISH,
            PageStatus::Scheduled => AdminPermission::PAGES_SCHEDULE,
            PageStatus::Unpublished => AdminPermission::PAGES_UNPUBLISH,
            PageStatus::Archived => AdminPermission::PAGES_ARCHIVE,
            PageStatus::Deleted => AdminPermission::PAGES_DELETE,
            PageStatus::Draft => match ($transition->from) {
                PageStatus::Deleted => AdminPermission::PAGES_DELETE,
                PageStatus::Archived => AdminPermission::PAGES_ARCHIVE,
                PageStatus::Unpublished => AdminPermission::PAGES_UNPUBLISH,
                default => AdminPermission::PAGES_SUBMIT_REVIEW,
            },
        };
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::TRANSITION && $subject instanceof PageTransition;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->decisionManager->decide($token, [self::permissionFor($subject)]);
    }
}
