<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Service;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Application\Usage\MediaUsageFinder;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Shared\Infrastructure\Upload\UploadValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Следит, чтобы каждая картинка сайта отдавалась с адаптивными превью (`responsive_image` строит `<picture>`
 * только для ассетов медиатеки с вариантами):
 * - файлы из `/uploads/media/`, на которые ссылается контент, но которых нет в медиатеке (перенесённые вручную
 *   или импортом), заносятся в медиатеку и получают превью;
 * - картинкам медиатеки без превью (загружены, когда в PHP не было WebP) или с неполным набором превью
 *   превью создаются заново.
 * Повторный запуск ничего не меняет.
 *
 * Запускается при каждом деплое, поэтому ошибка одного файла не должна останавливать остальные: файлы
 * обрабатываются по порядку, и «застрявший» файл иначе навсегда оставлял бы без превью все следующие.
 * Картинки больше лимитов загрузки ({@see UploadValidator}) в GD не открываются: несжатое изображение
 * может превысить memory_limit, а такой fatal error не перехватить.
 */
final readonly class MediaLibrarySynchronizer
{
    private const string IMPORT_FOLDER = 'imported';

    /** @var list<string> */
    private const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public function __construct(
        private MediaAssetRepositoryInterface $assets,
        private MediaUsageFinder $usage,
        private MediaOptimizer $optimizer,
        #[Autowire('%app.media_upload_dir%')]
        private string $mediaUploadDir,
        #[Autowire(service: 'monolog.logger.media')]
        private LoggerInterface $logger,
    ) {
    }

    public function sync(bool $dryRun = false): MediaSyncReport
    {
        $report = new MediaSyncReport();
        $this->importReferencedFiles($report, $dryRun);
        $this->createMissingVariants($report, $dryRun);

        return $report;
    }

    private function importReferencedFiles(MediaSyncReport $report, bool $dryRun): void
    {
        $known = $this->assets->publicPathMap();
        $paths = $this->usage->index()->paths();
        sort($paths);

        foreach ($paths as $path) {
            $path = rawurldecode((string) strtok($path, '?#'));
            if (isset($known[$path])) {
                continue;
            }

            $filename = substr($path, \strlen(MediaPathExtractor::PREFIX));
            // Только файлы в корне медиатеки: варианты и вложенные папки не заносим.
            if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '..')) {
                continue;
            }

            $absolutePath = $this->mediaUploadDir.'/'.$filename;
            if (!is_file($absolutePath)) {
                $report->missingFiles[] = $path;

                continue;
            }

            $info = @getimagesize($absolutePath);
            if ($info === false || !\in_array($info['mime'], self::IMAGE_TYPES, true)) {
                $report->skipped[] = $path;

                continue;
            }

            if ($this->exceedsUploadLimits($absolutePath, $info[0], $info[1])) {
                $report->tooLarge[] = $path;

                continue;
            }

            $hash = hash_file('sha256', $absolutePath);
            if ($hash !== false && $this->assets->findOneByFileHash($hash) instanceof MediaAsset) {
                $report->duplicates[] = $path;

                continue;
            }

            $known[$path] = 'pending';
            if ($dryRun) {
                $report->imported[] = $path;

                continue;
            }

            try {
                $optimized = $this->optimizer->optimize($absolutePath, $path, $info['mime'], $info[0], $info[1]);
                $asset = new MediaAsset(
                    $filename,
                    $filename,
                    $path,
                    $info['mime'],
                    $optimized->size,
                    $optimized->width,
                    $optimized->height,
                    $optimized->variants,
                    $hash === false ? null : $hash,
                );
                $asset->updateMetadata(null, null, null, self::IMPORT_FOLDER);
                $this->assets->save($asset);
                $report->imported[] = $path;
            } catch (Throwable $exception) {
                $this->fail($report, $path, 'import', $exception);
            }
        }
    }

    private function createMissingVariants(MediaSyncReport $report, bool $dryRun): void
    {
        // Без WebP/AVIF в PHP превью не появятся, а оригиналы зря перекодировались бы при каждом деплое.
        if (!$this->optimizer->canCreateVariants()) {
            return;
        }

        foreach ($this->assets->findLatest(100_000) as $asset) {
            if (!$this->needsPreviews($asset)) {
                continue;
            }

            $absolutePath = $this->mediaUploadDir.'/'.basename($asset->publicPath());
            if (!is_file($absolutePath)) {
                continue;
            }

            if ($this->exceedsUploadLimits($absolutePath, $asset->width(), $asset->height())) {
                $report->tooLarge[] = $asset->publicPath();

                continue;
            }

            if ($dryRun) {
                $report->variantsCreated[] = $asset->publicPath();

                continue;
            }

            try {
                $optimized = $this->optimizer->optimize($absolutePath, $asset->publicPath(), $asset->mimeType(), $asset->width(), $asset->height());
                if ($optimized->variants === []) {
                    // PHP не умеет WebP/AVIF.
                    continue;
                }

                $asset->replaceVariants($optimized->variants, $optimized->size);
                $this->assets->save($asset);
                $report->variantsCreated[] = $asset->publicPath();
            } catch (Throwable $exception) {
                $this->fail($report, $asset->publicPath(), 'previews', $exception);
            }
        }
    }

    private function exceedsUploadLimits(string $absolutePath, ?int $width, ?int $height): bool
    {
        return (int) filesize($absolutePath) > UploadValidator::MAX_SIZE_BYTES
            || ($width ?? 0) > UploadValidator::MAX_IMAGE_WIDTH
            || ($height ?? 0) > UploadValidator::MAX_IMAGE_HEIGHT;
    }

    private function fail(MediaSyncReport $report, string $path, string $stage, Throwable $exception): void
    {
        $report->failed[$path] = $exception::class.': '.$exception->getMessage();
        $this->logger->error('Media library sync failed for a file.', [
            'path' => $path,
            'stage' => $stage,
            'exception' => $exception,
        ]);
    }

    /**
     * Превью нужны картинке, у которой нет хотя бы одной из нужных ширин
     * (загружена до того, как появились 480/1024 px или оригинал до 1280 px стал попадать в превью).
     */
    private function needsPreviews(MediaAsset $asset): bool
    {
        $width = $asset->width();
        if ($width === null || $width <= 0 || !\in_array($asset->mimeType(), self::IMAGE_TYPES, true)) {
            return false;
        }

        $existing = [];
        foreach ($asset->variants() as $variant) {
            $existing[(int) $variant['width']] = true;
        }

        return array_any(MediaOptimizer::targetWidths($width), static fn (int $target): bool => !isset($existing[$target]));
    }
}
