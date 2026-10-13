<?php

declare(strict_types=1);

namespace App\Module\User\Domain\Repository;

use App\Module\User\Domain\Entity\AdminUser;

/**
 * Хранилище пользователей админки. Реализация — Doctrine (`DoctrineAdminUserRepository`), она же провайдер
 * пользователей для Symfony Security.
 */
interface AdminUserRepositoryInterface
{
    public function save(AdminUser $user): void;

    public function remove(AdminUser $user): void;

    /**
     * @param string $id ULID; некорректная строка — `null`
     */
    public function findById(string $id): ?AdminUser;

    public function findOneByEmail(string $email): ?AdminUser;

    /**
     * @return list<AdminUser>
     */
    public function findAllActive(): array;

    /**
     * Все пользователи, новые первыми. Пользователей админки единицы, поэтому без пагинации.
     *
     * @return list<AdminUser>
     */
    public function findAllNewestFirst(): array;
}
