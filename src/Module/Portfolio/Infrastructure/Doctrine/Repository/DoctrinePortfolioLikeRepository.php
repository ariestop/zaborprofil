<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Infrastructure\Doctrine\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioLike;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Repository\PortfolioLikeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PortfolioLike>
 */
final class DoctrinePortfolioLikeRepository extends ServiceEntityRepository implements PortfolioLikeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortfolioLike::class);
    }

    public function save(PortfolioLike $like): void
    {
        $this->getEntityManager()->persist($like);
        $this->getEntityManager()->flush();
    }

    public function remove(PortfolioLike $like): void
    {
        $this->getEntityManager()->remove($like);
        $this->getEntityManager()->flush();
    }

    public function findByVisitor(PortfolioProject $project, string $visitorHash): ?PortfolioLike
    {
        return $this->findOneBy(['project' => $project, 'visitorHash' => $visitorHash]);
    }

    public function countByIp(PortfolioProject $project, string $ipHash): int
    {
        return $this->count(['project' => $project, 'ipHash' => $ipHash]);
    }

    public function countByProjects(array $projectIds): array
    {
        $ids = self::binaryIds($projectIds);
        if ($ids === []) {
            return [];
        }

        /** @var list<array{projectId: Ulid|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.project) AS projectId, COUNT(l.id) AS total')
            ->andWhere('l.project IN (:ids)')->setParameter('ids', $ids)
            ->groupBy('l.project')
            ->getQuery()->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[self::idString($row['projectId'])] = (int) $row['total'];
        }

        return $result;
    }

    public function likedByVisitor(array $projectIds, string $visitorHash): array
    {
        $ids = self::binaryIds($projectIds);
        if ($ids === []) {
            return [];
        }

        /** @var list<array{projectId: Ulid|string}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.project) AS projectId')
            ->andWhere('l.project IN (:ids)')->setParameter('ids', $ids)
            ->andWhere('l.visitorHash = :visitor')->setParameter('visitor', $visitorHash)
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $row): string => self::idString($row['projectId']), $rows);
    }

    /**
     * @param list<string> $projectIds
     *
     * @return list<string>
     */
    private static function binaryIds(array $projectIds): array
    {
        return array_values(array_map(
            static fn (string $id): string => Ulid::fromString($id)->toBinary(),
            array_filter($projectIds, Ulid::isValid(...)),
        ));
    }

    private static function idString(Ulid|string $id): string
    {
        // IDENTITY() отдаёт колонку как есть: ULID в 16 байтах, а не объект.
        return (string) ($id instanceof Ulid ? $id : Ulid::fromString($id));
    }
}
