<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Domain;

use App\Module\User\Domain\AdminRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminRoleTest extends TestCase
{
    /**
     * @param list<string> $roles
     */
    #[DataProvider('adminAccessProvider')]
    public function testGrantsAdminAccess(array $roles, bool $expected): void
    {
        self::assertSame($expected, AdminRole::grantsAdminAccess($roles));
    }

    /**
     * @return iterable<string, array{0:list<string>, 1:bool}>
     */
    public static function adminAccessProvider(): iterable
    {
        yield 'super admin' => [[AdminRole::SUPER_ADMIN], true];
        yield 'admin' => [[AdminRole::ADMIN], true];
        yield 'editor' => [[AdminRole::EDITOR], true];
        yield 'seo' => [[AdminRole::SEO], true];
        yield 'manager' => [[AdminRole::MANAGER], true];
        yield 'no roles' => [[], false];
        yield 'only default user role' => [['ROLE_USER'], false];
        yield 'unknown role' => [['ROLE_GOD'], false];
        yield 'unknown plus manager' => [['ROLE_GOD', AdminRole::MANAGER], true];
    }
}
