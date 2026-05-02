<?php

declare(strict_types=1);

namespace App\Tests\Unit\Seo\Domain\Entity;

use App\Module\Seo\Domain\Entity\Redirect;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RedirectTest extends TestCase
{
    public function testNormalizesLocalPaths(): void
    {
        $redirect = new Redirect('old-page/', 'new-page/', 301);

        self::assertSame('/old-page/', $redirect->sourcePath());
        self::assertSame('/new-page/', $redirect->targetPath());
    }

    public function testAllowsAbsoluteTargetUrl(): void
    {
        $redirect = new Redirect('/old/', 'https://example.com/new/', 302);

        self::assertSame('https://example.com/new/', $redirect->targetPath());
        self::assertSame(302, $redirect->statusCode());
    }

    public function testRejectsInvalidStatusCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Redirect('/old/', '/new/', 200);
    }

    public function testRegistersHit(): void
    {
        $redirect = new Redirect('/old/', '/new/');

        $redirect->registerHit();

        self::assertSame(1, $redirect->hitCount());
        self::assertNotNull($redirect->lastHitAt());
    }
}
