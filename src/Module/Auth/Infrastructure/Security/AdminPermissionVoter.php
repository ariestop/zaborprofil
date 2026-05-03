<?php

declare(strict_types=1);

namespace App\Module\Auth\Infrastructure\Security;

use App\Module\Auth\Domain\Security\AdminPermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Centralizes current admin permissions until resource-specific voters need
 * domain object checks. Role hierarchy is repeated here intentionally because
 * custom voters receive raw token roles, not expanded hierarchy roles.
 *
 * @extends Voter<string, mixed>
 */
final class AdminPermissionVoter extends Voter
{
    /**
     * @var array<string, list<string>>
     */
    private const array PERMISSION_ROLES = [
        AdminPermission::PAGES_VIEW => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_CREATE => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_EDIT => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_PUBLISH => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_SUBMIT_REVIEW => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_APPROVE => ['ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_UNPUBLISH => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_SCHEDULE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_ARCHIVE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_VIEW_REVISIONS => ['ROLE_EDITOR', 'ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_ROLLBACK_REVISION => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_MANAGE_TEMPLATES => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::PAGES_DELETE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::BLOCKS_CREATE => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::BLOCKS_EDIT => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::BLOCKS_DELETE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::BLOCKS_REORDER => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::BLOCKS_CLONE => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::SEO_EDIT => ['ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::SEO_APPROVE => ['ROLE_SEO', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::MEDIA_UPLOAD => ['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::MEDIA_DELETE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::LEADS_VIEW => ['ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::LEADS_MANAGE => ['ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::CATALOG_VIEW => ['ROLE_EDITOR', 'ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::CATALOG_MANAGE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::SETTINGS_EDIT => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::USERS_MANAGE => ['ROLE_SUPER_ADMIN'],
        AdminPermission::SYSTEM_VIEW => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        AdminPermission::SYSTEM_MANAGE => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, AdminPermission::all(), true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $roles = $token->getRoleNames();
        $allowedRoles = self::PERMISSION_ROLES[$attribute] ?? [];
        return array_any($allowedRoles, fn ($allowedRole) => \in_array($allowedRole, $roles, true));
    }
}
