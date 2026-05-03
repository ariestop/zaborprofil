<?php

declare(strict_types=1);

namespace App\Module\Menu\Domain\Repository;

use App\Module\Menu\Domain\Entity\MenuItem;

interface MenuItemRepositoryInterface
{
    public function save(MenuItem $item): void;

    public function remove(MenuItem $item): void;

    public function get(string $id): MenuItem;

    /**
     * @return list<MenuItem>
     */
    public function findAllForAdmin(): array;

    /**
     * @return list<MenuItem>
     */
    public function findActiveByPosition(string $position): array;
}
