<?php

declare(strict_types=1);

namespace App\Module\Content\Application\Service;

use App\Module\User\Infrastructure\Repository\AdminUserRepository;

/**
 * Имена администраторов для подписей «кто изменил»: имя из профиля, иначе email.
 * Пользователей в админке единицы, поэтому справочник читается целиком одним запросом.
 */
final readonly class AdminDisplayNames
{
    public function __construct(private AdminUserRepository $users)
    {
    }

    /**
     * @return array<string, string> id администратора => подпись
     */
    public function all(): array
    {
        $names = [];
        foreach ($this->users->findAll() as $user) {
            $name = trim((string) $user->name());
            $names[(string) $user->id()] = $name !== '' ? $name : $user->email();
        }

        return $names;
    }
}
