<?php

declare(strict_types=1);

namespace App\Module\Settings\Domain\Repository;

use App\Module\Settings\Domain\Entity\Setting;

interface SettingRepositoryInterface
{
    public function save(Setting $setting): void;

    public function remove(Setting $setting): void;

    public function findOne(string $scope, string $key): ?Setting;

    /**
     * @return list<Setting>
     */
    public function findByScope(?string $scope = null): array;
}
