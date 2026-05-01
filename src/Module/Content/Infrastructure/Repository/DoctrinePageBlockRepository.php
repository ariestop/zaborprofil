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

    public function saveAll(iterable $blocks): void
    {
        $entityManager = $this->getEntityManager();

        $entityManager->wrapInTransaction(function () use ($blocks, $entityManager): void {
            foreach ($blocks as $block) {
                $entityManager->persist($block);
            }

            $entityManager->flush();
        });
    }

    public function remove(PageBlock $block): void
    {
        $block->page()->removeBlock($block);
        $this->getEntityManager()->remove($block);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): PageBlock
    {
        return $this->findById($id) ?? throw new ContentNotFoundException('Page block not found.');
    }

    public function findById(string $id): ?PageBlock
    {
        return $this->findOneBy(['id' => Ulid::fromString($id)]);
    }

    /**
     * @return list<PageBlock>
     */
    public function findByPage(string $pageId): array
    {
        return $this->findBy(['page' => Ulid::fromString($pageId)], ['position' => 'ASC']);
    }
}
