<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(int $id): ?Product
    {
        return $this->entityManager->getRepository(Product::class)->find($id);
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->entityManager->getRepository(Product::class)->findOneBy(['slug' => $slug]);
    }

    public function findAllWithCategories(int $page = 1, int $limit = 20): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p', 'c', 'v')
            ->from(Product::class, 'p')
            ->join('p.category', 'c')
            ->leftJoin('p.variants', 'v')
            ->orderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb);
        return iterator_to_array($paginator);
    }

    public function countAll(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(p.id)')
            ->from(Product::class, 'p');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
