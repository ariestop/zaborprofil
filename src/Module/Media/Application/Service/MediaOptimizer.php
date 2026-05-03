<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Service;

use GdImage;

final readonly class MediaOptimizer
{
    /**
     * @var list<int>
     */
    private const array THUMBNAIL_WIDTHS = [320, 768, 1280];

    public function optimize(string $absolutePath, string $publicPath, string $mimeType, ?int $width, ?int $height): MediaOptimizationResult
    {
        if ($width === null || $height === null || !$this->isSupportedImage($mimeType)) {
            return new MediaOptimizationResult((int) filesize($absolutePath), $width, $height, []);
        }

        $source = $this->loadImage($absolutePath, $mimeType);
        if (!$source instanceof GdImage) {
            return new MediaOptimizationResult((int) filesize($absolutePath), $width, $height, []);
        }

        $this->rewriteOriginal($source, $absolutePath, $mimeType);

        $variants = $this->createVariants($source, $absolutePath, $publicPath, $width, $height);
        imagedestroy($source);

        return new MediaOptimizationResult((int) filesize($absolutePath), $width, $height, $variants);
    }

    private function isSupportedImage(string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => \function_exists('imagecreatefromjpeg') && \function_exists('imagejpeg'),
            'image/png' => \function_exists('imagecreatefrompng') && \function_exists('imagepng'),
            'image/webp' => \function_exists('imagecreatefromwebp') && \function_exists('imagewebp'),
            'image/avif' => \function_exists('imagecreatefromavif') && \function_exists('imageavif'),
            default => false,
        };
    }

    private function loadImage(string $absolutePath, string $mimeType): ?GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($absolutePath),
            'image/png' => imagecreatefrompng($absolutePath),
            'image/webp' => imagecreatefromwebp($absolutePath),
            'image/avif' => imagecreatefromavif($absolutePath),
            default => false,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function rewriteOriginal(GdImage $image, string $absolutePath, string $mimeType): void
    {
        match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $absolutePath, 85),
            'image/png' => imagepng($image, $absolutePath, 6),
            'image/webp' => imagewebp($image, $absolutePath, 82),
            'image/avif' => imageavif($image, $absolutePath, 55),
            default => null,
        };
    }

    /**
     * @return list<array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}>
     */
    private function createVariants(GdImage $source, string $absolutePath, string $publicPath, int $width, int $height): array
    {
        $variants = [];
        $directory = \dirname($absolutePath).'/variants';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return [];
        }

        foreach (self::THUMBNAIL_WIDTHS as $targetWidth) {
            if ($targetWidth >= $width) {
                continue;
            }

            $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
            $thumbnail = $this->resample($source, $targetWidth, $targetHeight);
            $basename = pathinfo($absolutePath, PATHINFO_FILENAME).'-'.$targetWidth;

            if (\function_exists('imagewebp')) {
                $variantPath = $directory.'/'.$basename.'.webp';
                imagewebp($thumbnail, $variantPath, 82);
                $variants[] = $this->variant($publicPath, $variantPath, $targetWidth, $targetHeight, 'webp', 'image/webp');
            }

            if (\function_exists('imageavif')) {
                $variantPath = $directory.'/'.$basename.'.avif';
                imageavif($thumbnail, $variantPath, 55);
                $variants[] = $this->variant($publicPath, $variantPath, $targetWidth, $targetHeight, 'avif', 'image/avif');
            }

            imagedestroy($thumbnail);
        }

        return $variants;
    }

    private function resample(GdImage $source, int $width, int $height): GdImage
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('Media variant dimensions must be positive.');
        }

        $thumbnail = imagecreatetruecolor($width, $height);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $thumbnail;
    }

    /**
     * @return array{type: string, publicPath: string, width: int|null, height: int|null, mimeType: string, size: int}
     */
    private function variant(string $originalPublicPath, string $variantPath, int $width, int $height, string $type, string $mimeType): array
    {
        return [
            'type' => $type,
            'publicPath' => \dirname($originalPublicPath).'/variants/'.basename($variantPath),
            'width' => $width,
            'height' => $height,
            'mimeType' => $mimeType,
            'size' => (int) filesize($variantPath),
        ];
    }
}
