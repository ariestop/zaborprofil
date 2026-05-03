<?php

declare(strict_types=1);

namespace App\Module\Catalog\Infrastructure\Doctrine\Repository;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Enum\ProductStatus;
use App\Module\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Module\Catalog\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Product>
 */
final class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }

    public function remove(Product $product): void
    {
        $this->getEntityManager()->remove($product);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): Product
    {
        return $this->find(Ulid::fromString($id)) ?? throw new CatalogNotFoundException('Catalog product not found.');
    }

    public function findAllForAdmin(): array
    {
        /** @var list<Product> $result */
        $result = $this->createQueryBuilder('product')
            ->leftJoin('product.variants', 'variant')
            ->addSelect('variant')
            ->orderBy('product.updatedAt', 'DESC')
            ->addOrderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findPublishedByPath(string $path): ?Product
    {
        $product = $this->publishedQueryBuilder()
            ->andWhere('product.path = :path')
            ->setParameter('path', $this->normalizePath($path))
            ->getQuery()
            ->getOneOrNullResult();

        return $product instanceof Product ? $product : null;
    }

    public function findPublishedByCategory(?Category $category): array
    {
        $builder = $this->publishedQueryBuilder()
            ->orderBy('product.name', 'ASC');

        if ($category === null) {
            $builder->andWhere('product.category IS NULL');
        } else {
            $builder
                ->andWhere('IDENTITY(product.category) = :categoryId')
                ->setParameter('categoryId', $category->id(), 'ulid');
        }

        /** @var list<Product> $result */
        $result = $builder->getQuery()->getResult();

        return $result;
    }

    public function countPublishedIndexable(): int
    {
        return (int) $this->publishedIndexableQueryBuilder()
            ->select('COUNT(product.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPublishedIndexableSlice(int $limit, int $offset): array
    {
        /** @var list<Product> $result */
        $result = $this->publishedIndexableQueryBuilder()
            ->orderBy('product.path', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $result;
    }

    private function publishedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('product')
            ->leftJoin('product.variants', 'variant')
            ->addSelect('variant')
            ->andWhere('product.status = :status')
            ->setParameter('status', ProductStatus::Published);
    }

    private function publishedIndexableQueryBuilder(): QueryBuilder
    {
        return $this->publishedQueryBuilder()
            ->andWhere('product.indexable = :indexable')
            ->setParameter('indexable', true);
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized !== '' && !str_starts_with($normalized, '/')) {
            return '/'.$normalized;
        }

        return $normalized;
    }
}
