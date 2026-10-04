<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Entity;

use App\Module\Media\Domain\Entity\MediaAsset;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MediaAssetTest extends TestCase
{
    public function testAssetExposesVariants(): void
    {
        $asset = new MediaAsset(
            'source.png',
            'source.png',
            '/uploads/media/source.png',
            'image/png',
            123,
            640,
            480,
            [[
                'type' => 'webp',
                'publicPath' => '/uploads/media/variants/source-320.webp',
                'width' => 320,
                'height' => 240,
                'mimeType' => 'image/webp',
                'size' => 42,
            ]],
        );

        self::assertSame('/uploads/media/source.png', $asset->publicPath());
        self::assertSame('webp', $asset->variants()[0]['type']);
        self::assertSame($asset->variants(), $asset->toArray()['variants']);
    }

    public function testRejectsInvalidVariant(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MediaAsset(
            'source.png',
            'source.png',
            '/uploads/media/source.png',
            'image/png',
            123,
            640,
            480,
            [['type' => 'webp']],
        );
    }

    public function testMetadataIsNormalizedAndExposed(): void
    {
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480);
        self::assertNull($asset->toArray()['alt']);

        $asset->updateMetadata('  Забор из профнастила ', "\n");

        self::assertSame('Забор из профнастила', $asset->toArray()['alt']);
        self::assertNull($asset->toArray()['title']);
    }

    public function testRejectsTooLongMetadata(): void
    {
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480);

        $this->expectException(InvalidArgumentException::class);

        $asset->updateMetadata(str_repeat('a', 256), null);
    }
}
