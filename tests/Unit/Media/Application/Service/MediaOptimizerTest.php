<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Service;

use App\Module\Media\Application\Service\MediaOptimizer;
use PHPUnit\Framework\TestCase;

final class MediaOptimizerTest extends TestCase
{
    private ?string $temporaryDirectory = null;

    protected function setUp(): void
    {
        if (!\extension_loaded('gd') || !\function_exists('imagewebp')) {
            self::markTestSkipped('GD with WebP support is required for media optimizer variant test.');
        }

        $this->temporaryDirectory = sys_get_temp_dir().'/zaborprofil-media-test-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        if ($this->temporaryDirectory !== null) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    public function testCreatesWebpThumbnailVariantAndKeepsDimensions(): void
    {
        self::assertNotNull($this->temporaryDirectory);

        $path = $this->temporaryDirectory.'/source.png';
        $image = imagecreatetruecolor(640, 480);
        imagepng($image, $path);

        $result = (new MediaOptimizer())->optimize($path, '/uploads/media/source.png', 'image/png', 640, 480);

        self::assertSame(640, $result->width);
        self::assertSame(480, $result->height);
        self::assertGreaterThan(0, $result->size);
        self::assertNotEmpty($result->variants);
        self::assertSame('webp', $result->variants[0]['type']);
        self::assertSame('/uploads/media/variants/source-320.webp', $result->variants[0]['publicPath']);
        self::assertFileExists($this->temporaryDirectory.'/variants/source-320.webp');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    public function testKeepsTransparencyOfPngAndWebpOriginals(): void
    {
        self::assertNotNull($this->temporaryDirectory);

        foreach (['png' => 'image/png', 'webp' => 'image/webp'] as $extension => $mimeType) {
            $path = $this->temporaryDirectory.'/cutout.'.$extension;
            $image = imagecreatetruecolor(40, 60);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 255, 255, 255, 127));
            imagefilledrectangle($image, 10, 10, 29, 49, (int) imagecolorallocatealpha($image, 20, 30, 40, 0));
            if ($extension === 'png') {
                imagepng($image, $path);
            } else {
                imagewebp($image, $path, 90);
            }

            (new MediaOptimizer())->optimize($path, '/uploads/media/cutout.'.$extension, $mimeType, 40, 60);

            $stored = $extension === 'png' ? imagecreatefrompng($path) : imagecreatefromwebp($path);
            self::assertInstanceOf(\GdImage::class, $stored);
            self::assertSame(127, (imagecolorat($stored, 2, 2) >> 24) & 0x7F, $extension.': фон остаётся прозрачным');
            self::assertSame(0, (imagecolorat($stored, 20, 30) >> 24) & 0x7F, $extension.': фигура остаётся непрозрачной');
        }
    }

    public function testTargetWidthsIncludeOriginalUpToLargestPreview(): void
    {
        self::assertSame([320, 480, 640], MediaOptimizer::targetWidths(640));
        self::assertSame([320, 480, 768, 1024, 1280], MediaOptimizer::targetWidths(1280));
        self::assertSame([320, 480, 768, 1024, 1280], MediaOptimizer::targetWidths(1600), 'Крупнее 1280 px оригинал в превью не попадает.');
        self::assertSame([200], MediaOptimizer::targetWidths(200));
    }
}
