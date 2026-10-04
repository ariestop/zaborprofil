<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Domain\Entity;

use App\Module\Media\Domain\Entity\MediaAsset;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testDescriptionFolderAndHashAreNormalizedAndExposed(): void
    {
        $hash = str_repeat('AB', 32);
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480, [], $hash);

        self::assertSame(strtolower($hash), $asset->fileHash());
        self::assertNull($asset->folder());

        $asset->updateMetadata('Альт', 'Заголовок', '  Описание файла ', ' Заборы ');

        $data = $asset->toArray();
        self::assertSame('Описание файла', $data['description']);
        self::assertSame('Заборы', $data['folder']);
        self::assertSame(strtolower($hash), $data['fileHash']);

        $asset->updateMetadata('Альт', 'Заголовок', '', '');
        self::assertNull($asset->description());
        self::assertNull($asset->folder());
    }

    public function testAttachFileHashStoresDigest(): void
    {
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480);
        self::assertNull($asset->fileHash());

        $asset->attachFileHash(hash('sha256', 'content'));

        self::assertSame(hash('sha256', 'content'), $asset->fileHash());
    }

    public function testRejectsInvalidFileHash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480, [], 'not-a-hash');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidFolders(): iterable
    {
        yield 'slash' => ['a/b'];
        yield 'backslash' => ['a\\b'];
        yield 'control character' => ["a\x07b"];
        yield 'reserved none marker' => [MediaAsset::FOLDER_NONE];
        yield 'too long' => [str_repeat('a', MediaAsset::FOLDER_MAX_LENGTH + 1)];
    }

    #[DataProvider('invalidFolders')]
    public function testRejectsInvalidFolder(string $folder): void
    {
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480);

        $this->expectException(InvalidArgumentException::class);

        $asset->updateMetadata(null, null, null, $folder);
    }

    public function testRejectsTooLongDescription(): void
    {
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480);

        $this->expectException(InvalidArgumentException::class);

        $asset->updateMetadata(null, null, str_repeat('a', MediaAsset::DESCRIPTION_MAX_LENGTH + 1));
    }

    public function testAllPublicPathsIncludesVariantsWithoutDuplicates(): void
    {
        $variant = [
            'type' => 'webp',
            'publicPath' => '/uploads/media/variants/source-320.webp',
            'width' => 320,
            'height' => 240,
            'mimeType' => 'image/webp',
            'size' => 42,
        ];
        $asset = new MediaAsset('source.png', 'source.png', '/uploads/media/source.png', 'image/png', 123, 640, 480, [$variant, $variant]);

        self::assertSame(
            ['/uploads/media/source.png', '/uploads/media/variants/source-320.webp'],
            $asset->allPublicPaths(),
        );
    }

    public function testFocalPointIsStoredClearedAndValidated(): void
    {
        $asset = new MediaAsset('a.jpg', 'a.jpg', '/uploads/media/a.jpg', 'image/jpeg', 10, 100, 100);
        self::assertNull($asset->focalX());

        $asset->updateFocalPoint(0, 100);
        self::assertSame(0, $asset->toArray()['focalX']);
        self::assertSame(100, $asset->toArray()['focalY']);

        $asset->updateFocalPoint(null, null);
        self::assertNull($asset->focalX());
        self::assertNull($asset->focalY());
    }

    /**
     * @return iterable<string, array{int|null, int|null}>
     */
    public static function invalidFocalPoints(): iterable
    {
        yield 'only x' => [50, null];
        yield 'only y' => [null, 50];
        yield 'negative' => [-1, 50];
        yield 'too large' => [50, 101];
    }

    #[DataProvider('invalidFocalPoints')]
    public function testRejectsInvalidFocalPoint(?int $x, ?int $y): void
    {
        $asset = new MediaAsset('a.jpg', 'a.jpg', '/uploads/media/a.jpg', 'image/jpeg', 10, 100, 100);

        $this->expectException(InvalidArgumentException::class);
        $asset->updateFocalPoint($x, $y);
    }
}
