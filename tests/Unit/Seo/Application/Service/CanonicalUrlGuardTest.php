<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Application\Service;

use App\Module\Seo\Application\Service\CanonicalUrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CanonicalUrlGuardTest extends TestCase
{
    public function testAllowsEmptyCanonicalUrl(): void
    {
        $guard = new CanonicalUrlGuard('https://zaborprofil.test');

        $guard->assertAllowed(null);
        $guard->assertAllowed('');

        self::addToAssertionCount(2);
    }

    public function testAllowsConfiguredHostCaseInsensitively(): void
    {
        $guard = new CanonicalUrlGuard('https://zaborprofil.test');

        $guard->assertAllowed('https://ZABORPROFIL.test/catalog/');

        self::addToAssertionCount(1);
    }

    public function testRejectsExternalHost(): void
    {
        $guard = new CanonicalUrlGuard('https://zaborprofil.test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Canonical URL must belong to the configured SITE_URL host.');

        $guard->assertAllowed('https://example.com/catalog/');
    }
}
