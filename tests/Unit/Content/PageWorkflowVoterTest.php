<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Auth\Infrastructure\Security\AdminPermissionVoter;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\ValueObject\PageTransition;
use App\Module\Content\Infrastructure\Security\PageWorkflowVoter;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PageWorkflowVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, PageStatus, PageStatus, bool}>
     */
    public static function transitions(): iterable
    {
        yield 'editor submits review' => ['ROLE_EDITOR', PageStatus::Draft, PageStatus::Review, true];
        yield 'editor cannot approve' => ['ROLE_EDITOR', PageStatus::Review, PageStatus::Approved, false];
        yield 'editor cannot publish' => ['ROLE_EDITOR', PageStatus::Approved, PageStatus::Published, false];
        yield 'editor cannot schedule' => ['ROLE_EDITOR', PageStatus::Approved, PageStatus::Scheduled, false];
        yield 'seo approves' => ['ROLE_SEO', PageStatus::Review, PageStatus::Approved, true];
        yield 'seo cannot publish' => ['ROLE_SEO', PageStatus::Approved, PageStatus::Published, false];
        yield 'seo cannot cancel schedule' => ['ROLE_SEO', PageStatus::Scheduled, PageStatus::Approved, false];
        yield 'admin publishes' => ['ROLE_ADMIN', PageStatus::Approved, PageStatus::Published, true];
        yield 'admin schedules' => ['ROLE_ADMIN', PageStatus::Approved, PageStatus::Scheduled, true];
        yield 'admin cancels schedule' => ['ROLE_ADMIN', PageStatus::Scheduled, PageStatus::Approved, true];
        yield 'admin unpublishes' => ['ROLE_ADMIN', PageStatus::Published, PageStatus::Unpublished, true];
        yield 'editor cannot restore deleted' => ['ROLE_EDITOR', PageStatus::Deleted, PageStatus::Draft, false];
        yield 'manager has nothing' => ['ROLE_MANAGER', PageStatus::Draft, PageStatus::Review, false];
    }

    #[DataProvider('transitions')]
    public function testVoterMapsTransitionsToPermissions(string $role, PageStatus $from, PageStatus $to, bool $granted): void
    {
        $voter = new PageWorkflowVoter(new AccessDecisionManager([new AdminPermissionVoter()]));
        $token = new UsernamePasswordToken(new AdminUser('user@example.test', 'hash', [$role]), 'main', [$role]);

        self::assertSame(
            $granted ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
            $voter->vote($token, new PageTransition($from, $to), [PageWorkflowVoter::TRANSITION]),
        );
    }

    public function testEveryTargetStatusHasPermission(): void
    {
        foreach (PageStatus::cases() as $to) {
            foreach (PageStatus::cases() as $from) {
                self::assertContains(PageWorkflowVoter::permissionFor(new PageTransition($from, $to)), AdminPermission::all());
            }
        }
    }

    public function testVoterAbstainsForForeignSubject(): void
    {
        $voter = new PageWorkflowVoter(new AccessDecisionManager([new AdminPermissionVoter()]));
        $token = new UsernamePasswordToken(new AdminUser('a@example.test', 'hash', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'not-a-transition', [PageWorkflowVoter::TRANSITION]));
    }
}
