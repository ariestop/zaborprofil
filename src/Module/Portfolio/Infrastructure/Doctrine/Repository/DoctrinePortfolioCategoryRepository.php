<?php

declare(strict_types=1);

namespace App\Module\Portfolio\Infrastructure\Doctrine\Repository;

use App\Module\Portfolio\Domain\Entity\PortfolioCategory;
use App\Module\Portfolio\Domain\Exception\PortfolioNotFoundException;
use App\Module\Portfolio\Domain\Repository\PortfolioCategoryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PortfolioCategory>
 */
final class DoctrinePortfolioCategoryRepository extends ServiceEntityRepository implements PortfolioCategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PortfolioCategory::class);
    }

    public function save(PortfolioCategory $category): void
    {
        $this->getEntityManager()->persist($category);
        $this->getEntityManager()->flush();
    }

    public function remove(PortfolioCategory $category): void
    {
        $this->getEntityManager()->remove($category);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): PortfolioCategory
    {
        if (!Ulid::isValid($id)) {
            throw new PortfolioNotFoundException('Категория портфолио не найдена.');
        }

        return $this->find(Ulid::fromString($id)) ?? throw new PortfolioNotFoundException('Категория портфолио не найдена.');
    }

    public function findAllOrdered(): array
    {
        /** @var list<PortfolioCategory> $result */
        $result = $this->findBy([], ['sortOrder' => 'ASC', 'title' => 'ASC']);

        return $result;
    }

    public function findBySlug(string $slug): ?PortfolioCategory
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
