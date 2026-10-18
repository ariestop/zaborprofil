<?php

declare(strict_types=1);

namespace App\Tests\Unit\Media\Application\Upload;

use App\Module\Media\Application\Service\MediaOptimizer;
use App\Module\Media\Application\Upload\MediaUploadHandler;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;
use App\Shared\Infrastructure\Upload\UploadValidator;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Сбой после переноса файла в каталог медиа не оставляет на диске файлов, о которых медиатека не знает.
 */
final class MediaUploadHandlerTest extends TestCase
{
    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir().'/media-upload-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ($this->filesIn($this->uploadDir) as $file) {
            unlink($file);
        }
        foreach ([$this->uploadDir.'/variants', $this->uploadDir] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    public function testFailedSaveRemovesStoredFileAndVariants(): void
    {
        $handler = new MediaUploadHandler(new FailingMediaAssetRepository(), new UploadValidator(), new MediaOptimizer(), $this->uploadDir);

        try {
            $handler->upload($this->pngUpload(), 'Заборы');
            self::fail('A failed save must be reported.');
        } catch (RuntimeException $exception) {
            self::assertSame('Database is unavailable.', $exception->getMessage());
        }

        self::assertDirectoryExists($this->uploadDir, 'Файл успел попасть в каталог медиа до сбоя.');
        self::assertSame([], $this->filesIn($this->uploadDir));
    }

    private function pngUpload(): UploadedFile
    {
        // 320×320: при наличии GD оптимизатор успевает создать превью до сбоя записи.
        $path = tempnam(sys_get_temp_dir(), 'upload-').'.png';
        if (\function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(320, 320);
            imagepng($image, $path);
        } else {
            file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true));
        }

        return new UploadedFile($path, 'zabor.png', 'image/png', null, true);
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}

final class FailingMediaAssetRepository implements MediaAssetRepositoryInterface
{
    public function save(MediaAsset $asset): void
    {
        throw new RuntimeException('Database is unavailable.');
    }

    public function remove(MediaAsset $asset): void
    {
        throw new LogicException('Not used.');
    }

    public function get(string $id): MediaAsset
    {
        throw new LogicException('Not used.');
    }

    public function findLatest(int $limit = 100): array
    {
        return [];
    }

    public function search(MediaAssetCriteria $criteria): MediaAssetPage
    {
        throw new LogicException('Not used.');
    }

    public function findByPublicPaths(array $publicPaths): array
    {
        return [];
    }

    public function findOneByFileHash(string $fileHash): ?MediaAsset
    {
        return null;
    }

    public function findWithoutFileHash(int $limit): array
    {
        return [];
    }

    public function folders(): array
    {
        return [];
    }

    public function publicPathMap(): array
    {
        return [];
    }
}
