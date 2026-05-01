<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\UI\Web;

use App\Module\Seo\UI\Web\RobotsController;
use PHPUnit\Framework\TestCase;

final class RobotsControllerTest extends TestCase
{
    public function testProdAllowsAndDisallowsAdminApiAndPublishesSitemap(): void
    {
        $controller = new RobotsController('prod', 'https://zaborprofil.ru');

        $body = (string) $controller()->getContent();

        self::assertStringContainsString("User-agent: *\n", $body);
        self::assertStringContainsString("Allow: /\n", $body);
        self::assertStringContainsString("Disallow: /admin/\n", $body);
        self::assertStringContainsString("Disallow: /api/\n", $body);
        self::assertStringContainsString("Sitemap: https://zaborprofil.ru/sitemap.xml\n", $body);
    }

    public function testProdWithoutSiteUrlOmitsSitemapDirective(): void
    {
        $controller = new RobotsController('prod', '');

        $body = (string) $controller()->getContent();

        self::assertStringNotContainsString('Sitemap:', $body);
    }

    public function testStagingAndDevDisallowEverything(): void
    {
        $controller = new RobotsController('staging', 'https://staging.zaborprofil.ru');

        self::assertSame(
            "User-agent: *\nDisallow: /\n",
            (string) $controller()->getContent(),
        );
    }
}
