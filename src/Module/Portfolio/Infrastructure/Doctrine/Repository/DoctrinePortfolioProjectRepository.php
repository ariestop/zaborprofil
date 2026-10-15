<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Infrastructure\Doctrine\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Entity\PortfolioProject;
use App\Module\Portfolio\Domain\Enum\PortfolioStatus;
use App\Module\Portfolio\Domain\Exception\PortfolioNotFoundException;
use App\Module\Portfolio\Domain\Repository\PortfolioProjectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PortfolioProject>
 */
final class DoctrinePortfolioProjectRepository extends ServiceEntityRepository implements PortfolioProjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortfolioProject::class);
    }

    public function save(PortfolioProject $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }

    public function remove(PortfolioProject $project): void
    {
        $this->getEntityManager()->remove($project);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): PortfolioProject
    {
        if (!Ulid::isValid($id)) {
            throw new PortfolioNotFoundException('Работа портфолио не найдена.');
        }

        return $this->find(Ulid::fromString($id)) ?? throw new PortfolioNotFoundException('Работа портфолио не найдена.');
    }

    public function findAllForAdmin(): array
    {
        /** @var list<PortfolioProject> $result */
        $result = $this->withMedia($this->createQueryBuilder('p'))
            ->innerJoin('p.category', 'c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->getQuery()->getResult();

        return $result;
    }

    public function findByCategory(PortfolioCategory $category): array
    {
        /** @var list<PortfolioProject> $result */
        $result = $this->findBy(['category' => $category], ['sortOrder' => 'ASC']);

        return $result;
    }

    public function findPublishedByCategory(PortfolioCategory $category): array
    {
        /** @var list<PortfolioProject> $result */
        $result = $this->withMedia($this->published())
            ->andWhere('p.category = :category')->setParameter('category', $category->id(), 'ulid')
            ->orderBy('p.sortOrder', 'ASC')
            ->getQuery()->getResult();

        return $result;
    }

    public function findPublishedBySlug(PortfolioCategory $category, string $slug): ?PortfolioProject
    {
        /** @var PortfolioProject|null $result */
        $result = $this->withMedia($this->published())
            ->andWhere('p.category = :category')->setParameter('category', $category->id(), 'ulid')
            ->andWhere('p.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();

        return $result;
    }

    public function findLatestPublished(int $limit, ?PortfolioCategory $category = null): array
    {
        $qb = $this->published()->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.sortOrder', 'ASC')->setMaxResults($limit);
        if ($category !== null) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category->id(), 'ulid');
        }

        /** @var list<PortfolioProject> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findPublishedByIds(array $ids): array
    {
        $ulids = array_values(array_filter($ids, Ulid::isValid(...)));
        if ($ulids === []) {
            return [];
        }

        /** @var list<PortfolioProject> $found */
        $found = $this->published()
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $ulids))
            ->getQuery()->getResult();

        $byId = [];
        foreach ($found as $project) {
            $byId[(string) $project->id()] = $project;
        }

        return array_values(array_filter(array_map(static fn (string $id): ?PortfolioProject => $byId[(string) Ulid::fromString($id)] ?? null, $ulids)));
    }

    public function countPublishedByCategory(): array
    {
        /** @var list<array{categoryId: Ulid|string, total: int|string}> $rows */
        $rows = $this->published()
            ->select('IDENTITY(p.category) AS categoryId, COUNT(p.id) AS total')
            ->groupBy('p.category')
            ->getQuery()->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            // IDENTITY() отдаёт колонку как есть: ULID в 16 байтах, а не объект.
            $id = $row['categoryId'] instanceof Ulid ? $row['categoryId'] : Ulid::fromString($row['categoryId']);
            $result[(string) $id] = (int) $row['total'];
        }

        return $result;
    }

    public function countPublished(): int
    {
        return (int) $this->published()->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
    }

    public function findPublishedSlice(int $limit, int $offset): array
    {
        /** @var list<PortfolioProject> $result */
        $result = $this->published()->orderBy('p.publishedAt', 'ASC')->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)->setFirstResult($offset)
            ->getQuery()->getResult();

        return $result;
    }

    public function slugTaken(PortfolioCategory $category, string $slug, ?string $exceptId): bool
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)')
            ->andWhere('p.category = :category')->setParameter('category', $category->id(), 'ulid')
            ->andWhere('p.slug = :slug')->setParameter('slug', $slug);
        if ($exceptId !== null && Ulid::isValid($exceptId)) {
            $qb->andWhere('p.id <> :id')->setParameter('id', Ulid::fromString($exceptId), 'ulid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countByCategory(PortfolioCategory $category): int
    {
        return $this->count(['category' => $category]);
    }

    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :published')->setParameter('published', PortfolioStatus::Published);
    }

    private function withMedia(QueryBuilder $qb): QueryBuilder
    {
        return $qb->leftJoin('p.media', 'm')->addSelect('m');
    }
}
