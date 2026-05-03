<?php

declare(strict_types=1);

namespace App\Module\Menu\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Menu\Domain\Entity\MenuItem;
use App\Module\Menu\Domain\Repository\MenuItemRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
final class DoctrineMenuItemRepository extends ServiceEntityRepository implements MenuItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    public function save(MenuItem $item): void
    {
        $this->getEntityManager()->persist($item);
        $this->getEntityManager()->flush();
    }

    public function remove(MenuItem $item): void
    {
        $this->getEntityManager()->remove($item);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): MenuItem
    {
        return $this->find(Ulid::fromString($id)) ?? throw new ContentNotFoundException('Menu item not found.');
    }

    public function findAllForAdmin(): array
    {
        /** @var list<MenuItem> $result */
        $result = $this->findBy([], ['position' => 'ASC', 'sortOrder' => 'ASC']);

        return $result;
    }

    public function findActiveByPosition(string $position): array
    {
        /** @var list<MenuItem> $result */
        $result = $this->findBy(['position' => $position, 'active' => true], ['sortOrder' => 'ASC']);

        return $result;
    }
}
