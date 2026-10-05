<?php

declare(strict_types=1);

namespace App\Module\Media\Application\Usage;

use App\Module\Media\Domain\Entity\MediaAsset;

final class MediaUsageIndex
{
    /**
     * @var array<string, list<MediaUsageReference>>
     */
    private array $byPath = [];

    /**
     * @param iterable<MediaUsageReference> $references
     */
    public function __construct(iterable $references)
    {
        foreach ($references as $reference) {
            $this->byPath[$reference->path][] = $reference;
        }
    }

    /**
     * @param list<string> $paths
     *
     * @return list<MediaUsageReference>
     */
    public function forPaths(array $paths): array
    {
        $unique = [];
        foreach ($paths as $path) {
            foreach ($this->byPath[$path] ?? [] as $reference) {
                $unique[$reference->key()] ??= $reference;
            }
        }

        return array_values($unique);
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function forAsset(MediaAsset $asset): array
    {
        return $this->forPaths($asset->allPublicPaths());
    }

    public function count(MediaAsset $asset): int
    {
        return \count($this->forAsset($asset));
    }

    /**
     * @param array<string, string> $pathMap карта «путь => id ассета»
     *
     * @return list<string> идентификаторы ассетов, на которые есть хотя бы одна ссылка
     */
    public function usedAssetIds(array $pathMap): array
    {
        $ids = [];
        foreach (array_keys($this->byPath) as $path) {
            if (isset($pathMap[$path])) {
                $ids[$pathMap[$path]] = true;
            }
        }

        return array_keys($ids);
    }
}
