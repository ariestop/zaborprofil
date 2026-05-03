<?php

declare(strict_types=1);

namespace App\Module\Settings\Application\Service;

use App\Module\Settings\Domain\Entity\Setting;
use App\Module\Settings\Domain\Repository\SettingRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class SettingsService
{
    private const string CACHE_PREFIX = 'settings.';

    public function __construct(
        private SettingRepositoryInterface $settings,
        private CacheInterface $cache,
        private BusinessEventLogger $businessEvents,
    ) {
    }

    public function get(string $scope, string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->cacheKey($scope, $key), function (ItemInterface $item) use ($scope, $key, $default): mixed {
            $item->expiresAfter(86400);
            $setting = $this->settings->findOne($scope, $key);

            return $setting?->value() ?? $default;
        });
    }

    public function set(string $scope, string $key, mixed $value, ?string $description = null): Setting
    {
        $setting = $this->settings->findOne($scope, $key);
        if ($setting === null) {
            $setting = new Setting($scope, $key, $value, $description);
        } else {
            $setting->update($value, $description);
        }

        $this->settings->save($setting);
        $this->cache->delete($this->cacheKey($scope, $key));
        $this->businessEvents->log('setting.updated', [
            'scope' => $scope,
            'key' => $key,
        ]);

        return $setting;
    }

    public function delete(string $scope, string $key): void
    {
        $setting = $this->settings->findOne($scope, $key);
        if ($setting !== null) {
            $this->settings->remove($setting);
            $this->businessEvents->log('setting.deleted', [
                'scope' => $scope,
                'key' => $key,
            ]);
        }

        $this->cache->delete($this->cacheKey($scope, $key));
    }

    /**
     * @return list<Setting>
     */
    public function all(?string $scope = null): array
    {
        return $this->settings->findByScope($scope);
    }

    private function cacheKey(string $scope, string $key): string
    {
        return self::CACHE_PREFIX.$scope.'.'.$key;
    }
}
