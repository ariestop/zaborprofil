<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Responsive;

use App\Module\Media\Application\Usage\MediaPathExtractor;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;

/**
 * Сопоставляет прямой URL картинки из контента блока с ассетом медиатеки по `publicPath`.
 * Результаты хранятся только в памяти процесса: публичный кэш страниц не зависит от состояния медиатеки.
 */
final class ResponsiveImageResolver
{
    /**
     * @var array<string, ResponsiveImage|null>
     */
    private array $resolved = [];

    public function __construct(private readonly MediaAssetRepositoryInterface $assets)
    {
    }

    public function resolve(string $src): ?ResponsiveImage
    {
        $path = $this->normalize($src);
        if ($path === null) {
            return null;
        }

        if (!\array_key_exists($path, $this->resolved)) {
            $this->preload([$path]);
        }

        return $this->resolved[$path] ?? null;
    }

    /**
     * @param list<string> $paths
     */
    public function preload(array $paths): void
    {
        $missing = [];
        foreach ($paths as $path) {
            $normalized = $this->normalize($path);
            if ($normalized !== null && !\array_key_exists($normalized, $this->resolved)) {
                $missing[$normalized] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        $requested = array_keys($missing);
        foreach ($requested as $path) {
            $this->resolved[$path] = null;
        }

        foreach ($this->assets->findByPublicPaths($requested) as $asset) {
            $this->resolved[$asset->publicPath()] = $this->describe($asset);
        }
    }

    private function describe(MediaAsset $asset): ?ResponsiveImage
    {
        if (!str_starts_with($asset->mimeType(), 'image/')) {
            return null;
        }

        return ResponsiveImage::fromAsset($asset);
    }

    private function normalize(string $src): ?string
    {
        $src = trim($src);
        if (!str_starts_with($src, MediaPathExtractor::PREFIX)) {
            return null;
        }

        return rawurldecode(strtok($src, '?#'));
    }
}
