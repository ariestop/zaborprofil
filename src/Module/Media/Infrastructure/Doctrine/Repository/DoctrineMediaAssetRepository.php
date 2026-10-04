<?php

declare(strict_types=1);

namespace App\Module\Media\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use App\Module\Media\Domain\ValueObject\MediaAssetCriteria;
use App\Module\Media\Domain\ValueObject\MediaAssetPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            default => $query->orderBy('asset.createdAt', 'DESC')->addOrderBy('asset.id', 'DESC'),
        };

        /** @var list<MediaAsset> $items */
        $items = $query->getQuery()->getResult();

        return new MediaAssetPage($items, $total, $criteria->page, $criteria->perPage);
    }

    private function filteredQuery(MediaAssetCriteria $criteria): QueryBuilder
    {
        $query = $this->createQueryBuilder('asset');

        if ($criteria->search !== '') {
            $query
                ->andWhere('asset.originalName LIKE :search OR asset.alt LIKE :search OR asset.title LIKE :search')
                ->setParameter('search', '%'.addcslashes($criteria->search, '\\%_').'%');
        }

        if ($criteria->type === MediaAssetCriteria::TYPE_IMAGE) {
            $query->andWhere('asset.mimeType LIKE :imageMime')->setParameter('imageMime', 'image/%');
        } elseif ($criteria->type === MediaAssetCriteria::TYPE_DOCUMENT) {
            $query->andWhere('asset.mimeType NOT LIKE :imageMime')->setParameter('imageMime', 'image/%');
        }

        return $query;
    }
}
