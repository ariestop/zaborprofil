<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\Repository;

use App\Module\Media\Domain\Entity\MediaAsset;

interface MediaAssetRepositoryInterface
{
    public function save(MediaAsset $asset): void;

    public function remove(MediaAsset $asset): void;

    public function get(string $id): MediaAsset;

    /**
     * @return list<MediaAsset>
     */
    public function findLatest(int $limit = 100): array;
}
