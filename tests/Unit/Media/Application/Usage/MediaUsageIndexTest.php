<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Usage;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageFinder;
use App\Module\Media\Application\Usage\MediaUsageIndex;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Domain\Entity\MediaAsset;
use PHPUnit\Framework\TestCase;

final class MediaUsageIndexTest extends TestCase
{
    public function testExtractorFindsPathsInJsonHtmlAndAbsoluteUrls(): void
    {
        $text = '{"image":"https:\/\/zaborprofil.ru\/uploads\/media\/a.webp?v=1","html":"<img src=\"\/uploads\/media\/variants\/b-320.webp\"> <a href=\'/uploads/media/c.pdf\'>"}';

        self::assertSame(
            ['/uploads/media/a.webp', '/uploads/media/variants/b-320.webp', '/uploads/media/c.pdf'],
            MediaPathExtractor::extract($text),
        );
        self::assertSame([], MediaPathExtractor::extract(null));
        self::assertSame([], MediaPathExtractor::extract('/images/other.jpg'));
        self::assertSame(['/uploads/media/a.jpg'], MediaPathExtractor::extract('/uploads/media/a.jpg /uploads/media/a.jpg'));
    }

    public function testIndexMatchesOriginalAndVariantPathsAndDeduplicatesReferences(): void
    {
        $asset = new MediaAsset('a.jpg', 'a.jpg', '/uploads/media/a.jpg', 'image/jpeg', 100, 10, 10, [
            ['type' => 'webp', 'publicPath' => '/uploads/media/variants/a-320.webp', 'width' => 320, 'height' => 200, 'mimeType' => 'image/webp', 'size' => 10],
        ]);
        $other = new MediaAsset('b.jpg', 'b.jpg', '/uploads/media/b.jpg', 'image/jpeg', 100, null, null);

        $index = new MediaUsageIndex([
            new MediaUsageReference(MediaUsageReference::TYPE_PAGE_BLOCK, 'B1', '/uploads/media/a.jpg', 'Page', 'Block', '/admin/pages/P1/builder'),
            new MediaUsageReference(MediaUsageReference::TYPE_PAGE_BLOCK, 'B1', '/uploads/media/variants/a-320.webp', 'Page', 'Block', '/admin/pages/P1/builder'),
            new MediaUsageReference(MediaUsageReference::TYPE_MENU_ITEM, 'M1', '/uploads/media/variants/a-320.webp', 'Menu', 'Footer'),
        ]);

        self::assertSame(2, $index->count($asset));
        self::assertSame(0, $index->count($other));
        self::assertSame(
            [(string) $asset->id()],
            $index->usedAssetIds([
                '/uploads/media/a.jpg' => (string) $asset->id(),
                '/uploads/media/variants/a-320.webp' => (string) $asset->id(),
                '/uploads/media/b.jpg' => (string) $other->id(),
            ]),
        );
    }

    public function testFinderCollectsReferencesFromAllProviders(): void
    {
        $provider = static fn (string $path): MediaUsageProviderInterface => new readonly class ($path) implements MediaUsageProviderInterface {
            public function __construct(private string $path)
            {
            }

            public function references(): iterable
            {
                yield new MediaUsageReference(MediaUsageReference::TYPE_SETTING, $this->path, $this->path, 'S', 'L');
            }
        };

        $index = (new MediaUsageFinder([$provider('/uploads/media/x.jpg'), $provider('/uploads/media/y.jpg')]))->index();
        $asset = new MediaAsset('x.jpg', 'x.jpg', '/uploads/media/x.jpg', 'image/jpeg', 1, null, null);

        self::assertSame(1, $index->count($asset));
    }
}
