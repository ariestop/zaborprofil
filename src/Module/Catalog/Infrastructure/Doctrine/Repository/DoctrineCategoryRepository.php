<?php

declare(strict_types=1);

namespace App\Module\Catalog\Infrastructure\Doctrine\Repository;

use App\Module\Catalog\Domain\Entity\Category;
use App\Module\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Module\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Category>
 */
final class DoctrineCategoryRepository extends ServiceEntityRepository implements CategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function save(Category $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(Category $category): void
    {
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): Category
    {
        return $this->findById($id) ?? throw new CatalogNotFoundException('Catalog category not found.');
    }

    public function findById(?string $id): ?Category
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        return $this->find(Ulid::fromString($id));
    }

    public function findAllForAdmin(): array
    {
        /** @var list<Category> $result */
        $result = $this->findBy([], ['sortOrder' => 'ASC', 'title' => 'ASC']);

        return $result;
    }

    public function findActiveForPublic(): array
    {
        /** @var list<Category> $result */
        $result = $this->findBy(['active' => true], ['sortOrder' => 'ASC', 'title' => 'ASC']);

        return $result;
    }

    public function findActiveByPath(string $path): ?Category
    {
        return $this->findOneBy([
            'path' => $this->normalizePath($path),
            'active' => true,
        ]);
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
