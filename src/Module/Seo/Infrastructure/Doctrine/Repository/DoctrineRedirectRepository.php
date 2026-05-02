<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Doctrine\Repository;

use App\Module\Seo\Domain\Entity\Redirect;
use App\Module\Seo\Domain\Repository\RedirectRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
