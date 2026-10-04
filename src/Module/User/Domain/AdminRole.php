<?php

declare(strict_types=1);

namespace App\Module\User\Domain;

final class AdminRole
{
    public const string SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const string ADMIN = 'ROLE_ADMIN';
    public const string EDITOR = 'ROLE_EDITOR';
    public const string SEO = 'ROLE_SEO';
    public const string MANAGER = 'ROLE_MANAGER';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN, self::EDITOR, self::SEO, self::MANAGER];
    }

    /**
     * Роли, дающие доступ к управлению пользователями и системе.
     *
     * @return list<string>
     */
    public static function administrative(): array
    {
        return [self::SUPER_ADMIN, self::ADMIN];
    }

    public static function isKnown(string $role): bool
    {
        return \in_array($role, self::all(), true);
    }

    /**
     * @param list<string> $roles
     */
    public static function hasAdministrative(array $roles): bool
    {
        return array_any($roles, static fn (string $role): bool => \in_array($role, self::administrative(), true));
    }
}
