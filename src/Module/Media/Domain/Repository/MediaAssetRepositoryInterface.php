<?php

declare(strict_types=1);

namespace App\Module\Media\Domain\Repository;

use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;

interface MediaAssetRepositoryInterface
{
    public function save(MediaAsset $asset): void;

    public function remove(MediaAsset $asset): void;

    public function get(string $id): MediaAsset;

    /**
     * @return list<MediaAsset>
     */
    public function findLatest(int $limit = 100): array;

    public function search(MediaAssetCriteria $criteria): MediaAssetPage;

    /**
     * Ассеты по точному публичному пути оригинала (варианты не учитываются).
     *
     * @param list<string> $publicPaths
     *
     * @return list<MediaAsset>
     */
    public function findByPublicPaths(array $publicPaths): array;

    public function findOneByFileHash(string $fileHash): ?MediaAsset;

    /**
     * @return list<MediaAsset>
     */
    public function findWithoutFileHash(int $limit): array;

    /**
     * @return list<array{name: string, count: int}>
     */
    public function folders(): array;

    /**
     * Карта «публичный путь файла или варианта» => «идентификатор ассета».
     *
     * @return array<string, string>
     */
    public function publicPathMap(): array;
}
