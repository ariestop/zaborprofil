<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\PageRevision;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageRevisionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PageRevision>
 */
final class DoctrinePageRevisionRepository extends ServiceEntityRepository implements PageRevisionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageRevision::class);
    }

    public function save(PageRevision $revision): void
    {
        $this->getEntityManager()->persist($revision);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): PageRevision
    {
        return $this->find(Ulid::fromString($id)) ?? throw new ContentNotFoundException('Page revision not found.');
    }

    public function nextVersionForPage(string $pageId): int
    {
        $latest = $this->createQueryBuilder('revision')
            ->select('MAX(revision.version)')
            ->andWhere('revision.page = :pageId')
            ->setParameter('pageId', Ulid::fromString($pageId), UlidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) ($latest ?? 0)) + 1;
    }

    public function findByPage(string $pageId): array
    {
        /** @var list<PageRevision> $result */
        $result = $this->createQueryBuilder('revision')
            ->andWhere('revision.page = :pageId')
            ->setParameter('pageId', Ulid::fromString($pageId), UlidType::NAME)
            ->orderBy('revision.version', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
