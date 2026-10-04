<?php

declare(strict_types=1);

namespace App\Module\Auth\Infrastructure\Security;

use App\Module\User\Domain\AdminRole;
use App\Module\User\Infrastructure\Doctrine\Entity\AdminUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Enforces account-status invariants for AdminUser at every authentication
 * step (pre- and post-credentials), so a deactivated administrator cannot
 * log in or keep an active session. After credentials are verified the account
 * must also hold at least one admin role (Editor/SEO/Manager/Admin/Super Admin).
 */
final class AdminUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AdminUser) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Account is disabled.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof AdminUser) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Account is disabled.');
        }

        if (!AdminRole::grantsAdminAccess($user->getRoles())) {
            throw new CustomUserMessageAccountStatusException('У учётной записи нет доступа к админ-панели.');
        }
    }
}
