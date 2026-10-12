<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Upload;

use App\Module\Media\Application\Service\MediaOptimizer;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Shared\Infrastructure\Upload\UploadValidator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * Загрузка файла в медиатеку: проверка, поиск дубликата по содержимому, перенос в каталог медиа,
 * оптимизация с превью и запись в БД.
 *
 * Если после переноса что-то упало (оптимизация, запись в БД), файл и созданные превью удаляются:
 * иначе на диске копились бы файлы, о которых медиатека не знает.
 */
final readonly class MediaUploadHandler
{
    private const string PUBLIC_PREFIX = '/uploads/media/';

    public function __construct(
        private MediaAssetRepositoryInterface $assets,
        private UploadValidator $uploadValidator,
        private MediaOptimizer $mediaOptimizer,
        private string $mediaUploadDir,
    ) {
    }

    public function upload(UploadedFile $file, ?string $folder): MediaUploadResult
    {
        $validated = $this->uploadValidator->validate($file);
        $folder = MediaAsset::normalizeFolder($folder);
        $fileHash = hash_file('sha256', $file->getPathname());
        if ($fileHash === false) {
            throw new RuntimeException('Uploaded file hash cannot be calculated.');
        }

        $duplicate = $this->assets->findOneByFileHash($fileHash);
        if ($duplicate instanceof MediaAsset) {
            return new MediaUploadResult($duplicate, true);
        }

        if (!is_dir($this->mediaUploadDir) && !mkdir($this->mediaUploadDir, 0775, true) && !is_dir($this->mediaUploadDir)) {
            throw new RuntimeException('Media upload directory cannot be created.');
        }
        $originalName = $file->getClientOriginalName();
        $storedFile = $file->move($this->mediaUploadDir, $validated->safeFilename);

        try {
            $publicPath = self::PUBLIC_PREFIX.$validated->safeFilename;
            $optimized = $this->mediaOptimizer->optimize(
                $storedFile->getPathname(),
                $publicPath,
                $validated->mimeType,
                $validated->width,
                $validated->height,
            );

            $asset = new MediaAsset(
                $originalName,
                $validated->safeFilename,
                $publicPath,
                $validated->mimeType,
                $optimized->size,
                $optimized->width,
                $optimized->height,
                $optimized->variants,
                $fileHash,
            );
            if ($folder !== null) {
                $asset->updateMetadata(null, null, null, $folder);
            }
            $this->assets->save($asset);
        } catch (Throwable $exception) {
            $this->removeStoredFiles($validated->safeFilename);

            throw $exception;
        }

        return new MediaUploadResult($asset, false);
    }

    /**
     * Удаляет сохранённый оригинал и его превью (`variants/<имя>-<ширина>.webp|avif`).
     * Имя файла уникально (ULID из {@see UploadValidator}), поэтому чужие файлы под маску не попадают.
     */
    private function removeStoredFiles(string $safeFilename): void
    {
        $directory = rtrim($this->mediaUploadDir, '/');
        $paths = [$directory.'/'.$safeFilename];
        $variants = glob($directory.'/variants/'.pathinfo($safeFilename, PATHINFO_FILENAME).'-*');
        if ($variants !== false) {
            $paths = [...$paths, ...$variants];
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
