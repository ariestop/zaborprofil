<?php

declare(strict_types=1);

namespace App\Module\Media\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Media\Domain\Entity\MediaAsset;
use App\Module\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
