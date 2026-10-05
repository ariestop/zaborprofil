<?php

declare(strict_types=1);

namespace App\Module\Media\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<MediaAsset>
 */
final class DoctrineMediaAssetRepository extends ServiceEntityRepository implements MediaAssetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaAsset::class);
    }

    public function save(MediaAsset $asset): void
    {
        $this->getEntityManager()->persist($asset);
        $this->getEntityManager()->flush();
    }

    public function remove(MediaAsset $asset): void
    {
        $this->getEntityManager()->remove($asset);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): MediaAsset
    {
        return $this->find(Ulid::fromString($id)) ?? throw new ContentNotFoundException('Media asset not found.');
    }

    public function findLatest(int $limit = 100): array
    {
        /** @var list<MediaAsset> $result */
        $result = $this->createQueryBuilder('asset')
            ->orderBy('asset.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function search(MediaAssetCriteria $criteria): MediaAssetPage
    {
        $total = (int) $this->filteredQuery($criteria)
            ->select('COUNT(asset.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $query = $this->filteredQuery($criteria)
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->perPage);

        match ($criteria->sort) {
            MediaAssetCriteria::SORT_OLDEST => $query->orderBy('asset.createdAt', 'ASC')->addOrderBy('asset.id', 'ASC'),
            MediaAssetCriteria::SORT_NAME => $query->orderBy('asset.originalName', 'ASC')->addOrderBy('asset.id', 'ASC'),
            MediaAssetCriteria::SORT_SIZE => $query->orderBy('asset.size', 'DESC')->addOrderBy('asset.id', 'DESC'),
            MediaAssetCriteria::SORT_SIZE_ASC => $query->orderBy('asset.size', 'ASC')->addOrderBy('asset.id', 'ASC'),
            default => $query->orderBy('asset.createdAt', 'DESC')->addOrderBy('asset.id', 'DESC'),
        };

        /** @var list<MediaAsset> $items */
        $items = $query->getQuery()->getResult();

        return new MediaAssetPage($items, $total, $criteria->page, $criteria->perPage);
    }

    public function findByPublicPaths(array $publicPaths): array
    {
        if ($publicPaths === []) {
            return [];
        }

        /** @var list<MediaAsset> $result */
        $result = $this->createQueryBuilder('asset')
            ->where('asset.publicPath IN (:paths)')
            ->setParameter('paths', array_values(array_unique($publicPaths)), ArrayParameterType::STRING)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findOneByFileHash(string $fileHash): ?MediaAsset
    {
        return $this->findOneBy(['fileHash' => strtolower($fileHash)], ['createdAt' => 'ASC']);
    }

    public function findWithoutFileHash(int $limit): array
    {
        /** @var list<MediaAsset> $result */
        $result = $this->createQueryBuilder('asset')
            ->where('asset.fileHash IS NULL')
            ->orderBy('asset.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function folders(): array
    {
        /** @var list<array{folder: string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('asset')
            ->select('asset.folder AS folder', 'COUNT(asset.id) AS total')
            ->where('asset.folder IS NOT NULL')
            ->groupBy('asset.folder')
            ->orderBy('asset.folder', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => ['name' => $row['folder'], 'count' => (int) $row['total']],
            $rows,
        );
    }

    public function publicPathMap(): array
    {
        /** @var list<array{id: Ulid, publicPath: string, variants: list<array{publicPath: string}>}> $rows */
        $rows = $this->createQueryBuilder('asset')
            ->select('asset.id AS id', 'asset.publicPath AS publicPath', 'asset.variants AS variants')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $map[$row['publicPath']] = $id;
            foreach ($row['variants'] as $variant) {
                $map[$variant['publicPath']] = $id;
            }
        }

        return $map;
    }

    private function filteredQuery(MediaAssetCriteria $criteria): QueryBuilder
    {
        $query = $this->createQueryBuilder('asset');

        if ($criteria->search !== '') {
            $query
                ->andWhere('asset.originalName LIKE :search OR asset.alt LIKE :search OR asset.title LIKE :search OR asset.description LIKE :search')
                ->setParameter('search', '%'.addcslashes($criteria->search, '\\%_').'%');
        }

        if ($criteria->type === MediaAssetCriteria::TYPE_IMAGE) {
            $query->andWhere('asset.mimeType LIKE :imageMime')->setParameter('imageMime', 'image/%');
        } elseif ($criteria->type === MediaAssetCriteria::TYPE_DOCUMENT) {
            $query->andWhere('asset.mimeType NOT LIKE :imageMime')->setParameter('imageMime', 'image/%');
        }

        if ($criteria->format !== null) {
            $query->andWhere('asset.mimeType IN (:formatMimes)')
                ->setParameter('formatMimes', MediaAssetCriteria::FORMAT_MIME_TYPES[$criteria->format], ArrayParameterType::STRING);
        }

        if ($criteria->folder === MediaAsset::FOLDER_NONE) {
            $query->andWhere('asset.folder IS NULL');
        } elseif ($criteria->folder !== null) {
            $query->andWhere('asset.folder = :folder')->setParameter('folder', $criteria->folder);
        }

        if ($criteria->createdFrom !== null) {
            $query->andWhere('asset.createdAt >= :createdFrom')->setParameter('createdFrom', $criteria->createdFrom);
        }

        if ($criteria->createdTo !== null) {
            $query->andWhere('asset.createdAt <= :createdTo')->setParameter('createdTo', $criteria->createdTo);
        }

        $this->applyUsageFilter($query, $criteria);

        return $query;
    }

    private function applyUsageFilter(QueryBuilder $query, MediaAssetCriteria $criteria): void
    {
        if ($criteria->usage === null) {
            return;
        }

        $ids = array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $criteria->usedAssetIds);

        if ($criteria->usage === MediaAssetCriteria::USAGE_USED) {
            if ($ids === []) {
                $query->andWhere('1 = 0');

                return;
            }

            $query->andWhere('asset.id IN (:usedIds)')->setParameter('usedIds', $ids, ArrayParameterType::BINARY);

            return;
        }

        if ($ids !== []) {
            $query->andWhere('asset.id NOT IN (:usedIds)')->setParameter('usedIds', $ids, ArrayParameterType::BINARY);
        }
    }
}
