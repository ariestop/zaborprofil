<?php

declare(strict_types=1);

namespace App\Tests\Unit\Settings\Application\Service;

use App\Module\Settings\Application\Service\SettingsService;
use App\Module\Settings\Domain\Entity\Setting;
use App\Module\Settings\Domain\Repository\SettingRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SettingsServiceTest extends TestCase
{
    public function testStoresAndReadsSettingFromCacheBackedService(): void
    {
        $repository = new InMemorySettingRepository();
        $service = new SettingsService($repository, new ArrayAdapter(), new BusinessEventLogger(new NullLogger()));

        $setting = $service->set('seo', 'robots.body', "User-agent: *\nAllow: /\n");

        self::assertSame('seo', $setting->scope());
        self::assertSame("User-agent: *\nAllow: /\n", $service->get('seo', 'robots.body'));
        self::assertSame('fallback', $service->get('missing', 'key', 'fallback'));
    }

    public function testUpdatesCachedSettingAfterWrite(): void
    {
        $repository = new InMemorySettingRepository();
        $service = new SettingsService($repository, new ArrayAdapter(), new BusinessEventLogger(new NullLogger()));

        $service->set('site', 'name', 'Old');
        self::assertSame('Old', $service->get('site', 'name'));

        $service->set('site', 'name', 'New');
        self::assertSame('New', $service->get('site', 'name'));
    }
}

final class InMemorySettingRepository implements SettingRepositoryInterface
{
    /**
     * @var array<string, Setting>
     */
    private array $items = [];

    public function save(Setting $setting): void
    {
        $this->items[$this->key($setting->scope(), $setting->key())] = $setting;
    }

    public function remove(Setting $setting): void
    {
        unset($this->items[$this->key($setting->scope(), $setting->key())]);
    }

    public function findOne(string $scope, string $key): ?Setting
    {
        return $this->items[$this->key($scope, $key)] ?? null;
    }

    public function findByScope(?string $scope = null): array
    {
        if ($scope === null) {
            return array_values($this->items);
        }

        return array_values(array_filter(
            $this->items,
            static fn (Setting $setting): bool => $setting->scope() === $scope,
        ));
    }

    private function key(string $scope, string $key): string
    {
        return $scope.'.'.$key;
    }
}
