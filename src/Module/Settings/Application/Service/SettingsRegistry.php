<?php

declare(strict_types=1);

namespace App\Module\Settings\Application\Service;

final readonly class SettingsRegistry
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function getString(string $scope, string $key, string $default = ''): string
    {
        $value = $this->settings->get($scope, $key, $default);

        return \is_string($value) ? $value : $default;
    }

    public function getBool(string $scope, string $key, bool $default = false): bool
    {
        $value = $this->settings->get($scope, $key, $default);

        return \is_bool($value) ? $value : $default;
    }

    public function getInt(string $scope, string $key, int $default = 0): int
    {
        $value = $this->settings->get($scope, $key, $default);

        return \is_int($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $default
     *
     * @return array<string, mixed>
     */
    public function getObject(string $scope, string $key, array $default = []): array
    {
        $value = $this->settings->get($scope, $key, $default);

        if (!\is_array($value)) {
            return $default;
        }

        $result = [];
        foreach ($value as $itemKey => $itemValue) {
            if (!\is_string($itemKey)) {
                return $default;
            }

            $result[$itemKey] = $itemValue;
        }

        return $result;
    }
}
