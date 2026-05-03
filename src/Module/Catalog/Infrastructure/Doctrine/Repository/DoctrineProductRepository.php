<?php

declare(strict_types=1);

namespace App\Module\Catalog\Infrastructure\Doctrine\Repository;

use App\Module\Catalog\Domain\Entity\Product;
use App\Module\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Module\Catalog\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
