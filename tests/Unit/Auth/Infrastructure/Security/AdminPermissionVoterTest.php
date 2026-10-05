<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Module\Auth\Domain\Security\AdminPermission;
use App\Module\Auth\Infrastructure\Security\AdminPermissionVoter;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class AdminPermissionVoterTest extends TestCase
{
    public function testEditorCanEditPagesButCannotManageSettings(): void
    {
        $voter = new AdminPermissionVoter();
        $token = new UsernamePasswordToken(new AdminUser('editor@example.test', 'hash', ['ROLE_EDITOR']), 'main', ['ROLE_EDITOR']);

        self::assertSame(1, $voter->vote($token, null, [AdminPermission::PAGES_EDIT]));
        self::assertSame(-1, $voter->vote($token, null, [AdminPermission::SETTINGS_EDIT]));
    }

    public function testSuperAdminCanManageUsers(): void
    {
        $voter = new AdminPermissionVoter();
        $token = new UsernamePasswordToken(new AdminUser('root@example.test', 'hash', ['ROLE_SUPER_ADMIN']), 'main', ['ROLE_SUPER_ADMIN']);

        self::assertSame(1, $voter->vote($token, null, [AdminPermission::USERS_MANAGE]));
    }

    public function testAdminCanManageUsers(): void
    {
        $voter = new AdminPermissionVoter();
        $token = new UsernamePasswordToken(new AdminUser('admin@example.test', 'hash', ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']);

        self::assertSame(1, $voter->vote($token, null, [AdminPermission::USERS_MANAGE]));
    }

    public function testManagerWorksWithLeadsButCannotExportThem(): void
    {
        $voter = new AdminPermissionVoter();
        $token = new UsernamePasswordToken(new AdminUser('manager@example.test', 'hash', ['ROLE_MANAGER']), 'main', ['ROLE_MANAGER']);

        self::assertSame(1, $voter->vote($token, null, [AdminPermission::LEADS_VIEW]));
        self::assertSame(1, $voter->vote($token, null, [AdminPermission::LEADS_MANAGE]));
        self::assertSame(-1, $voter->vote($token, null, [AdminPermission::LEADS_EXPORT]));
    }

    public function testOnlyAdministrativeRolesCanExportLeads(): void
    {
        $voter = new AdminPermissionVoter();

        foreach (['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'] as $role) {
            $token = new UsernamePasswordToken(new AdminUser('user@example.test', 'hash', [$role]), 'main', [$role]);
            self::assertSame(1, $voter->vote($token, null, [AdminPermission::LEADS_EXPORT]), $role);
        }

        foreach (['ROLE_EDITOR', 'ROLE_SEO'] as $role) {
            $token = new UsernamePasswordToken(new AdminUser('user@example.test', 'hash', [$role]), 'main', [$role]);
            self::assertSame(-1, $voter->vote($token, null, [AdminPermission::LEADS_VIEW]), $role);
            self::assertSame(-1, $voter->vote($token, null, [AdminPermission::LEADS_EXPORT]), $role);
        }
    }
}
