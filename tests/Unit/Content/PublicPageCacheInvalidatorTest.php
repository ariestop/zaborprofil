<?php

declare(strict_types=1);

namespace App\Tests\Unit\Content;

use App\Module\Content\Application\Service\PublicHttpCachePurgerInterface;
use App\Module\Content\Application\Service\PublicPageCacheInvalidator;
use App\Tests\Support\Content\RecordingHttpCachePurger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

final class PublicPageCacheInvalidatorTest extends TestCase
{
    public function testInvalidatePurgesNormalizedPathFromHttpCache(): void
    {
        $purger = new RecordingHttpCachePurger();

        $this->invalidator($purger)->invalidate('zabory/');

        self::assertSame(['/zabory/'], $purger->paths);
        self::assertSame(0, $purger->purgeAllCalls);
    }

    public function testInvalidateManyPurgesEachPathOnce(): void
    {
        $purger = new RecordingHttpCachePurger();

        $this->invalidator($purger)->invalidateMany(['/old/', 'old/', '/new/']);

        self::assertSame(['/old/', '/new/'], $purger->paths);
    }

    public function testInvalidateAllPurgesWholeHttpCache(): void
    {
        $purger = new RecordingHttpCachePurger();

        $this->invalidator($purger)->invalidateAll();

        self::assertSame(1, $purger->purgeAllCalls);
        self::assertSame([], $purger->paths);
    }

    private function invalidator(PublicHttpCachePurgerInterface $purger): PublicPageCacheInvalidator
    {
        return new PublicPageCacheInvalidator(new TagAwareAdapter(new ArrayAdapter()), $purger);
    }
}
