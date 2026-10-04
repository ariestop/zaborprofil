<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Doctrine\Repository;

use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use App\Module\Seo\Domain\Repository\RedirectSearchCriteria;
use App\Module\Seo\Domain\Repository\RedirectSearchResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Redirect>
 */
final class DoctrineRedirectRepository extends ServiceEntityRepository implements RedirectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirect::class);
    }

    public function save(Redirect $redirect): void
    {
        $this->getEntityManager()->persist($redirect);
        $this->getEntityManager()->flush();
    }

    public function saveAll(array $redirects): void
    {
        foreach ($redirects as $redirect) {
            $this->getEntityManager()->persist($redirect);
        }

        $this->getEntityManager()->flush();
    }

    public function remove(Redirect $redirect): void
    {
        $this->getEntityManager()->remove($redirect);
        $this->getEntityManager()->flush();
    }

    public function findById(string $id): ?Redirect
    {
        if (!Ulid::isValid($id)) {
            return null;
        }

        return $this->find(Ulid::fromString($id));
    }

    public function findActiveBySourcePath(string $sourcePath): ?Redirect
    {
        return $this->findOneBy([
            'sourcePath' => Redirect::normalizeSourcePath($sourcePath),
            'active' => true,
        ]);
    }

    public function findBySourcePath(string $sourcePath): ?Redirect
    {
        return $this->findOneBy(['sourcePath' => Redirect::normalizeSourcePath($sourcePath)]);
    }

    public function findAllOrdered(): array
    {
        /** @var list<Redirect> $result */
        $result = $this->createQueryBuilder('redirect')
            ->orderBy('redirect.sourcePath', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findAllActive(): array
    {
        /** @var list<Redirect> $result */
        $result = $this->createQueryBuilder('redirect')
            ->where('redirect.active = true')
            ->orderBy('redirect.sourcePath', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function search(RedirectSearchCriteria $criteria): RedirectSearchResult
    {
        $builder = $this->createQueryBuilder('redirect');

        $query = $criteria->query !== null ? trim($criteria->query) : '';
        if ($query !== '') {
            $builder
                ->andWhere('redirect.sourcePath LIKE :query OR redirect.targetPath LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '\\%_').'%');
        }

        if ($criteria->active !== null) {
            $builder->andWhere('redirect.active = :active')->setParameter('active', $criteria->active);
        }

        $total = (int) (clone $builder)
            ->select('COUNT(redirect.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sortField = match ($criteria->sort) {
            RedirectSearchCriteria::SORT_HITS => 'redirect.hitCount',
            RedirectSearchCriteria::SORT_UPDATED => 'redirect.updatedAt',
            RedirectSearchCriteria::SORT_LAST_HIT => 'redirect.lastHitAt',
            default => 'redirect.sourcePath',
        };

        $perPage = max(1, $criteria->perPage);

        /** @var list<Redirect> $items */
        $items = $builder
            ->orderBy($sortField, $criteria->descending ? 'DESC' : 'ASC')
            ->addOrderBy('redirect.sourcePath', 'ASC')
            ->setFirstResult((max(1, $criteria->page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new RedirectSearchResult($items, $total);
    }

    public function findActiveSourcePaths(array $sourcePaths): array
    {
        if ($sourcePaths === []) {
            return [];
        }

        /** @var list<array{sourcePath: string}> $rows */
        $rows = $this->createQueryBuilder('redirect')
            ->select('redirect.sourcePath AS sourcePath')
            ->where('redirect.sourcePath IN (:paths)')
            ->andWhere('redirect.active = true')
            ->setParameter('paths', $sourcePaths)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['sourcePath'], $rows);
    }

    public function counts(): array
    {
        $total = (int) $this->createQueryBuilder('redirect')
            ->select('COUNT(redirect.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $active = (int) $this->createQueryBuilder('redirect')
            ->select('COUNT(redirect.id)')
            ->where('redirect.active = true')
            ->getQuery()
            ->getSingleScalarResult();

        return ['total' => $total, 'active' => $active];
    }
}
