<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
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
