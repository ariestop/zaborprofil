<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Service;

use App\Module\Media\Application\Service\MediaLibrarySynchronizer;
use App\Module\Media\Application\Service\MediaOptimizer;
use App\Module\Media\Application\Usage\MediaUsageFinder;
use App\Module\Media\Application\Usage\MediaUsageProviderInterface;
use App\Module\Media\Application\Usage\MediaUsageReference;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Tests\Support\Media\InMemoryMediaAssets;
use PHPUnit\Framework\TestCase;

final class MediaLibrarySynchronizerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (!\function_exists('imagecreatetruecolor') || !\function_exists('imagejpeg')) {
            self::markTestSkipped('GD is not available.');
        }

        $this->dir = sys_get_temp_dir().'/media-sync-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/variants', 0775, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir) && is_dir($this->dir)) {
            foreach ([...(glob($this->dir.'/variants/*') ?: []), ...(glob($this->dir.'/*.*') ?: [])] as $file) {
                unlink($file);
            }
            rmdir($this->dir.'/variants');
            rmdir($this->dir);
        }
    }

    public function testImportsReferencedFilesOutsideLibraryAndIsIdempotent(): void
    {
        $this->jpeg('fence.jpg', 1280, 960);
        $this->jpeg('known.jpg', 800, 600, 40);
        file_put_contents($this->dir.'/notes.txt', 'not an image');
        $assets = new InMemoryMediaAssets();
        $known = new MediaAsset('known.jpg', 'known.jpg', '/uploads/media/known.jpg', 'image/jpeg', 100, 800, 600, [['type' => 'webp', 'publicPath' => '/uploads/media/variants/known-320.webp', 'width' => 320, 'height' => 240, 'mimeType' => 'image/webp', 'size' => 10]]);
        $assets->save($known);

        $synchronizer = $this->synchronizer($assets, [
            '/uploads/media/fence.jpg',
            '/uploads/media/known.jpg',
            '/uploads/media/variants/known-320.webp',
            '/uploads/media/missing.jpg',
            '/uploads/media/notes.txt',
        ]);

        $dry = $synchronizer->sync(true);
        self::assertSame(['/uploads/media/fence.jpg'], $dry->imported);
        self::assertCount(1, $assets->all(), 'Пробный запуск ничего не сохраняет.');

        $report = $synchronizer->sync();
        self::assertSame(['/uploads/media/fence.jpg'], $report->imported);
        self::assertSame(['/uploads/media/missing.jpg'], $report->missingFiles);
        self::assertSame(['/uploads/media/notes.txt'], $report->skipped);

        $imported = $assets->byPath('/uploads/media/fence.jpg');
        self::assertInstanceOf(MediaAsset::class, $imported);
        self::assertSame('image/jpeg', $imported->mimeType());
        self::assertSame(1280, $imported->width());
        self::assertSame('imported', $imported->folder());
        self::assertNotNull($imported->fileHash());
        if (\function_exists('imagewebp')) {
            $webp = array_values(array_filter($imported->variants(), static fn (array $variant): bool => $variant['mimeType'] === 'image/webp'));
            self::assertSame([320, 480, 768, 1024, 1280], array_map(static fn (array $variant): ?int => $variant['width'], $webp));
            self::assertFileExists($this->dir.'/variants/fence-1280.webp');
        }

        $again = $synchronizer->sync();
        self::assertSame([], $again->imported, 'Повторный запуск ничего не заносит.');
        self::assertCount(2, $assets->all());
    }

    public function testCreatesPreviewsForLibraryImagesWithoutVariants(): void
    {
        if (!\function_exists('imagewebp')) {
            self::markTestSkipped('GD without WebP support.');
        }

        $this->jpeg('old.jpg', 1000, 500);
        $assets = new InMemoryMediaAssets();
        $old = new MediaAsset('old.jpg', 'old.jpg', '/uploads/media/old.jpg', 'image/jpeg', 100, 1000, 500);
        $assets->save($old);

        $report = $this->synchronizer($assets, [])->sync();

        self::assertSame(['/uploads/media/old.jpg'], $report->variantsCreated);
        self::assertNotSame([], $old->variants());
        self::assertSame([], $this->synchronizer($assets, [])->sync()->variantsCreated, 'Ассету с превью повторно ничего не создаётся.');
    }

    public function testDuplicateContentUnderAnotherPathIsNotImportedTwice(): void
    {
        $this->jpeg('copy.jpg', 640, 480, 10);
        $hash = hash_file('sha256', $this->dir.'/copy.jpg');
        self::assertIsString($hash);
        $assets = new InMemoryMediaAssets();
        $assets->save(new MediaAsset('orig.jpg', 'orig.jpg', '/uploads/media/orig.jpg', 'image/jpeg', 100, 640, 480, [], $hash));

        $report = $this->synchronizer($assets, ['/uploads/media/copy.jpg'])->sync();

        self::assertSame(['/uploads/media/copy.jpg'], $report->duplicates);
        self::assertSame([], $report->imported);
    }

    /**
     * @param list<string> $paths
     */
    private function synchronizer(InMemoryMediaAssets $assets, array $paths): MediaLibrarySynchronizer
    {
        $provider = new readonly class ($paths) implements MediaUsageProviderInterface {
            /**
             * @param list<string> $paths
             */
            public function __construct(private array $paths)
            {
            }

            /**
             * @return iterable<MediaUsageReference>
             */
            public function references(): iterable
            {
                foreach ($this->paths as $path) {
                    yield new MediaUsageReference(MediaUsageReference::TYPE_PAGE_BLOCK, 'page-1', $path, 'Страница', 'Блок');
                }
            }
        };

        return new MediaLibrarySynchronizer($assets, new MediaUsageFinder([$provider]), new MediaOptimizer(), $this->dir);
    }

    /**
     * @param int<1, max>  $width
     * @param int<1, max>  $height
     * @param int<0, 215>  $seed
     */
    private function jpeg(string $name, int $width, int $height, int $seed = 0): void
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertNotFalse($image);
        $color = imagecolorallocate($image, 40 + $seed, 120, 60);
        self::assertNotFalse($color);
        imagefill($image, 0, 0, $color);
        imagejpeg($image, $this->dir.'/'.$name, 85);
    }
}
