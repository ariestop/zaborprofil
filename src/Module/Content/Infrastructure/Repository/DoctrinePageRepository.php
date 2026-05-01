<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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

    public function get(Ulid $id): Page
    {
        return $this->findById($id) ?? throw new ContentNotFoundException('Page not found.');
    }

    public function findById(Ulid $id): ?Page
    {
        return $this->findOneBy(['id' => $id]);
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

    public function existsByPath(string $path, ?Ulid $excludeId = null): bool
    {
        $builder = $this->createQueryBuilder('page')
            ->select('COUNT(page.id)')
            ->andWhere('page.path = :path')
            ->setParameter('path', $this->normalizePath($path));

        if ($excludeId !== null) {
            $builder
                ->andWhere('page.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $builder->getQuery()->getSingleScalarResult() > 0;
    }

    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized !== '' && !str_starts_with($normalized, '/')) {
            return '/' . $normalized;
        }

        return $normalized;
    }
}
