<?php

declare(strict_types=1);

namespace App\Tests\Support\Media;

use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;
use LogicException;

/**
 * Медиатека в памяти для unit-тестов.
 */
final class InMemoryMediaAssets implements MediaAssetRepositoryInterface
{
    /** @var array<string, MediaAsset> */
    private array $assets = [];

    public function save(MediaAsset $asset): void
    {
        $this->assets[(string) $asset->id()] = $asset;
    }

    public function remove(MediaAsset $asset): void
    {
        unset($this->assets[(string) $asset->id()]);
    }

    public function get(string $id): MediaAsset
    {
        return $this->assets[$id] ?? throw new LogicException('Not found.');
    }

    public function findLatest(int $limit = 100): array
    {
        return \array_slice(array_values($this->assets), 0, $limit);
    }

    public function search(MediaAssetCriteria $criteria): MediaAssetPage
    {
        throw new LogicException('Not used.');
    }

    public function findByPublicPaths(array $publicPaths): array
    {
        return array_values(array_filter($this->assets, static fn (MediaAsset $asset): bool => \in_array($asset->publicPath(), $publicPaths, true)));
    }

    public function findOneByFileHash(string $fileHash): ?MediaAsset
    {
        foreach ($this->assets as $asset) {
            if ($asset->fileHash() === $fileHash) {
                return $asset;
            }
        }

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
        $map = [];
        foreach ($this->assets as $id => $asset) {
            foreach ($asset->allPublicPaths() as $path) {
                $map[$path] = (string) $id;
            }
        }

        return $map;
    }

    /**
     * @return list<MediaAsset>
     */
    public function all(): array
    {
        return array_values($this->assets);
    }

    public function byPath(string $path): ?MediaAsset
    {
        return $this->findByPublicPaths([$path])[0] ?? null;
    }
}
