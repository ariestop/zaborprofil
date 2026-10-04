<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Responsive;

use App\Module\Media\Domain\Entity\MediaAsset;

/**
 * Описание изображения медиатеки для публичной разметки `<picture>`.
 */
final readonly class ResponsiveImage
{
    /**
     * @param list<ResponsiveImageSource> $sources источники в порядке приоритета (AVIF, затем WebP)
     */
    public function __construct(
        public string $src,
        public ?int $width,
        public ?int $height,
        public ?string $alt,
        public ?int $focalX,
        public ?int $focalY,
        public array $sources,
    ) {
    }

    public static function fromAsset(MediaAsset $asset): self
    {
        return new self(
            $asset->publicPath(),
            $asset->width(),
            $asset->height(),
            $asset->alt(),
            $asset->focalX(),
            $asset->focalY(),
            self::sources($asset),
        );
    }

    /**
     * @return list<ResponsiveImageSource>
     */
    private static function sources(MediaAsset $asset): array
    {
        $byMime = [];
        foreach ($asset->variants() as $variant) {
            if ($variant['width'] === null || $variant['width'] <= 0) {
                continue;
            }
            $byMime[$variant['mimeType']][$variant['width']] = $variant['publicPath'];
        }

        $originalWidth = $asset->width();
        if ($originalWidth !== null && $originalWidth > 0 && isset($byMime[$asset->mimeType()])) {
            $byMime[$asset->mimeType()][$originalWidth] = $asset->publicPath();
        }

        $sources = [];
        foreach (ResponsiveImageSource::PRIORITY as $mimeType) {
            if (!isset($byMime[$mimeType])) {
                continue;
            }
            $candidates = $byMime[$mimeType];
            ksort($candidates);
            $sources[] = new ResponsiveImageSource($mimeType, $candidates);
        }

        return $sources;
    }
}
