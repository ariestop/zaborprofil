<?php

declare(strict_types=1);

namespace App\Module\Catalog\Infrastructure\Doctrine\Repository;

use App\Module\Catalog\Domain\Entity\Variant;
use App\Module\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Module\Catalog\Domain\Repository\VariantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Variant>
 */
final class DoctrineVariantRepository extends ServiceEntityRepository implements VariantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Variant::class);
    }

    public function save(Variant $variant): void
    {
        $this->getEntityManager()->persist($variant);
        $this->getEntityManager()->flush();
    }

    public function remove(Variant $variant): void
    {
        $this->getEntityManager()->remove($variant);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): Variant
    {
        return $this->find(Ulid::fromString($id)) ?? throw new CatalogNotFoundException('Catalog variant not found.');
    }
}
