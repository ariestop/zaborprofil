<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use App\Module\Content\Domain\Repository\PageSearchCriteria;
use App\Module\Content\Domain\Repository\PageSearchResult;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Page>
 */
final class DoctrinePageRepository extends ServiceEntityRepository implements PageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    public function save(Page $page): void
    {
        $this->getEntityManager()->persist($page);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): Page
    {
        return $this->findById($id) ?? throw new ContentNotFoundException('Page not found.');
    }

    public function findById(string $id): ?Page
    {
        return $this->findOneBy(['id' => $this->toUlid($id)]);
    }

    public function findPreviewById(string $id): ?Page
    {
        return $this->findOneBy([
            'id' => $this->toUlid($id),
            'deletedAt' => null,
        ]);
    }

    public function findOneByPath(string $path): ?Page
    {
        return $this->findOneBy(['path' => $this->normalizePath($path)]);
    }

    public function findPublishedByPath(string $path): ?Page
    {
        return $this->findOneBy([
            'path' => $this->normalizePath($path),
            'status' => PageStatus::Published,
            'deletedAt' => null,
        ]);
    }

    /**
     * @return list<Page>
     */
    public function findAllPublishedIndexable(): array
    {
        /** @var list<Page> $result */
        $result = $this->publishedIndexableQueryBuilder()
            ->orderBy('page.path', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countPublishedIndexable(): int
    {
        return (int) $this->publishedIndexableQueryBuilder()
            ->select('COUNT(page.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPublishedIndexableSlice(int $limit, int $offset): array
    {
        /** @var list<Page> $result */
        $result = $this->publishedIndexableQueryBuilder()
            ->orderBy('page.path', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function existsByPath(string $path, ?string $excludeId = null): bool
    {
        $builder = $this->createQueryBuilder('page')
            ->select('COUNT(page.id)')
            ->andWhere('page.path = :path')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('path', $this->normalizePath($path));

        if (null !== $excludeId) {
            $builder
                ->andWhere('page.id != :excludeId')
                ->setParameter('excludeId', $this->toUlid($excludeId), UlidType::NAME);
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @return list<string>
     */
    public function findPublishedPathsBySeoTitle(string $seoTitle, string $excludeId, int $limit = 5): array
    {
        /** @var list<array{path: string}> $rows */
        $rows = $this->createQueryBuilder('page')
            ->select('page.path AS path')
            ->andWhere('COALESCE(page.metaTitle, page.title) = :seoTitle')
            ->andWhere('page.status = :status')
            ->andWhere('page.deletedAt IS NULL')
            ->andWhere('page.id != :excludeId')
            ->setParameter('seoTitle', $seoTitle)
            ->setParameter('status', PageStatus::Published)
            ->setParameter('excludeId', $this->toUlid($excludeId), UlidType::NAME)
            ->orderBy('page.path', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['path'], $rows);
    }

    /**
     * @return list<Page>
     */
    public function findAllForAdmin(): array
    {
        /** @var list<Page> $result */
        $result = $this->createQueryBuilder('page')
            ->andWhere('page.deletedAt IS NULL')
            ->orderBy('page.updatedAt', 'DESC')
            ->addOrderBy('page.path', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function searchForAdmin(PageSearchCriteria $criteria): PageSearchResult
    {
        $builder = $this->createQueryBuilder('page')
            ->andWhere('page.deletedAt IS NULL');

        $query = $criteria->query === null ? '' : trim($criteria->query);
        if ($query !== '') {
            $builder
                ->andWhere('page.title LIKE :query OR page.path LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '%_\\').'%');
        }

        if ($criteria->status !== null) {
            $builder
                ->andWhere('page.status = :status')
                ->setParameter('status', $criteria->status);
        }

        $total = (int) (clone $builder)
            ->select('COUNT(page.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $perPage = $criteria->normalizedPerPage();

        /** @var list<Page> $items */
        $items = $builder
            ->orderBy('page.updatedAt', 'DESC')
            ->addOrderBy('page.path', 'ASC')
            ->setFirstResult(($criteria->normalizedPage() - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new PageSearchResult($items, $total);
    }

    public function findDueForScheduledPublish(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Page> $result */
        $result = $this->createQueryBuilder('page')
            ->andWhere('page.status = :status')
            ->andWhere('page.scheduledPublishAt IS NOT NULL')
            ->andWhere('page.scheduledPublishAt <= :now')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('status', PageStatus::Scheduled)
            ->setParameter('now', $now)
            ->orderBy('page.scheduledPublishAt', 'ASC')
            ->addOrderBy('page.id', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findDueForScheduledUnpublish(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Page> $result */
        $result = $this->createQueryBuilder('page')
            ->andWhere('page.status = :status')
            ->andWhere('page.scheduledUnpublishAt IS NOT NULL')
            ->andWhere('page.scheduledUnpublishAt <= :now')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('status', PageStatus::Published)
            ->setParameter('now', $now)
            ->orderBy('page.scheduledUnpublishAt', 'ASC')
            ->addOrderBy('page.id', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countOverdueSchedules(DateTimeImmutable $threshold): int
    {
        $publish = (int) $this->createQueryBuilder('page')
            ->select('COUNT(page.id)')
            ->andWhere('page.status = :scheduled')
            ->andWhere('page.scheduledPublishAt IS NOT NULL')
            ->andWhere('page.scheduledPublishAt <= :threshold')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('scheduled', PageStatus::Scheduled)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();

        $unpublish = (int) $this->createQueryBuilder('page')
            ->select('COUNT(page.id)')
            ->andWhere('page.status = :published')
            ->andWhere('page.scheduledUnpublishAt IS NOT NULL')
            ->andWhere('page.scheduledUnpublishAt <= :threshold')
            ->andWhere('page.deletedAt IS NULL')
            ->setParameter('published', PageStatus::Published)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();

        return $publish + $unpublish;
    }

    private function publishedIndexableQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('page')
            ->andWhere('page.status = :status')
            ->andWhere('page.deletedAt IS NULL')
            ->andWhere('page.indexable = :indexable')
            ->setParameter('status', PageStatus::Published)
            ->setParameter('indexable', true);
    }

    private function toUlid(string $id): Ulid
    {
        return Ulid::fromString($id);
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ('' !== $normalized && !str_starts_with($normalized, '/')) {
            return '/'.$normalized;
        }

        return $normalized;
    }
}
