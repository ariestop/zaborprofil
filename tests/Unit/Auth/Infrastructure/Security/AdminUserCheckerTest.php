<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth\Infrastructure\Security;

use App\Module\Auth\Infrastructure\Security\AdminUserChecker;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class AdminUserCheckerTest extends TestCase
{
    public function testIgnoresNonAdminUsers(): void
    {
        $checker = new AdminUserChecker();
        $checker->checkPreAuth(new InMemoryUser('user@example.com', null));
        $checker->checkPostAuth(new InMemoryUser('user@example.com', null));

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsActiveAdmin(): void
    {
        $checker = new AdminUserChecker();
        $checker->checkPreAuth($this->makeAdmin(active: true));
        $checker->checkPostAuth($this->makeAdmin(active: true));

        $this->expectNotToPerformAssertions();
    }

    public function testBlocksDisabledAdminPreAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new AdminUserChecker())->checkPreAuth($this->makeAdmin(active: false));
    }

    public function testBlocksDisabledAdminPostAuth(): void
    {
        $this->expectException(CustomUserMessageAccountStatusException::class);

        (new AdminUserChecker())->checkPostAuth($this->makeAdmin(active: false));
    }

    private function makeAdmin(bool $active): AdminUser
    {
        $admin = new AdminUser('admin@example.com', 'hash');

        if (!$active) {
            $reflection = new ReflectionProperty(AdminUser::class, 'active');
            $reflection->setValue($admin, false);
        }

        return $admin;
    }
}
