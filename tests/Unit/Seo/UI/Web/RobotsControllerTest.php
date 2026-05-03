<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\UI\Web;

use App\Module\Seo\Application\Service\RobotsTxtManager;
use App\Module\Seo\UI\Web\RobotsController;
use App\Module\Settings\Application\Service\SettingsRegistry;
use App\Module\Settings\Application\Service\SettingsService;
use App\Module\Settings\Domain\Entity\Setting;
use App\Module\Settings\Domain\Repository\SettingRepositoryInterface;
use App\Shared\Application\Logging\BusinessEventLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RobotsControllerTest extends TestCase
{
    public function testProdAllowsAndDisallowsAdminApiAndPublishesSitemap(): void
    {
        $controller = $this->controller('prod', 'https://zaborprofil.ru');

        $body = (string) $controller()->getContent();

        self::assertStringContainsString("User-agent: *\n", $body);
        self::assertStringContainsString("Allow: /\n", $body);
        self::assertStringContainsString("Disallow: /admin/\n", $body);
        self::assertStringContainsString("Disallow: /api/\n", $body);
        self::assertStringContainsString("Sitemap: https://zaborprofil.ru/sitemap.xml\n", $body);
    }

    public function testProdWithoutSiteUrlOmitsSitemapDirective(): void
    {
        $controller = $this->controller('prod', '');

        $body = (string) $controller()->getContent();

        self::assertStringNotContainsString('Sitemap:', $body);
    }

    public function testStagingAndDevDisallowEverything(): void
    {
        $controller = $this->controller('staging', 'https://staging.zaborprofil.ru');

        self::assertSame(
            "User-agent: *\nDisallow: /\n",
            (string) $controller()->getContent(),
        );
    }

    private function controller(string $environment, string $siteUrl): RobotsController
    {
        $settings = new SettingsService(
            new class () implements SettingRepositoryInterface {
                public function save(Setting $setting): void
                {
                }

                public function remove(Setting $setting): void
                {
                }

                public function findOne(string $scope, string $key): ?Setting
                {
                    return null;
                }

                public function findByScope(?string $scope = null): array
                {
                    return [];
                }
            },
            new ArrayAdapter(),
            new BusinessEventLogger(new NullLogger()),
        );

        return new RobotsController(new RobotsTxtManager(new SettingsRegistry($settings), $environment, $siteUrl));
    }
}
