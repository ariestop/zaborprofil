<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\PageBlock;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageBlockRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PageBlock>
 */
final class DoctrinePageBlockRepository extends ServiceEntityRepository implements PageBlockRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageBlock::class);
    }

    public function save(PageBlock $block): void
    {
        $this->getEntityManager()->persist($block);
        $this->getEntityManager()->flush();
    }

    public function remove(PageBlock $block): void
    {
        $block->page()->removeBlock($block);
        $this->getEntityManager()->remove($block);
        $this->getEntityManager()->flush();
    }

    public function get(Ulid $id): PageBlock
    {
        return $this->findById($id) ?? throw new ContentNotFoundException('Page block not found.');
    }

    public function findById(Ulid $id): ?PageBlock
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @return list<PageBlock>
     */
    public function findByPage(Ulid $pageId): array
    {
        return $this->findBy(['page' => $pageId], ['position' => 'ASC']);
    }
}
