<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Service;

use App\Module\Media\Application\Service\MediaOptimizer;
use PHPUnit\Framework\TestCase;

final class MediaOptimizerTest extends TestCase
{
    private string $temporaryDirectory;

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
        $this->removeDirectory($this->temporaryDirectory);
    }

    public function testCreatesWebpThumbnailVariantAndKeepsDimensions(): void
    {
        $path = $this->temporaryDirectory.'/source.png';
        $image = imagecreatetruecolor(640, 480);
        imagepng($image, $path);
        imagedestroy($image);

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
}
