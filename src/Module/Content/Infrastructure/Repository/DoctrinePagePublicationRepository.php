<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\Page;
use App\Module\Content\Domain\Entity\PagePublication;
use App\Module\Content\Domain\Enum\PageStatus;
use App\Module\Content\Domain\Repository\PagePublicationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<PagePublication>
 */
final class DoctrinePagePublicationRepository extends ServiceEntityRepository implements PagePublicationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PagePublication::class);
    }

    public function save(PagePublication $publication): void
    {
        $this->getEntityManager()->persist($publication);
        $this->getEntityManager()->flush();
    }

    public function getOrCreate(Page $page): PagePublication
    {
        $publication = $this->findOneBy(['page' => $page->id()]);
        if ($publication instanceof PagePublication) {
            return $publication;
        }

        return new PagePublication($page);
    }

    public function findByPage(string $pageId): ?PagePublication
    {
        return $this->findOneBy(['page' => Ulid::fromString($pageId)]);
    }

    public function findByPages(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        /** @var list<PagePublication> $publications */
        $publications = $this->createQueryBuilder('publication')
            ->leftJoin('publication.publishedRevision', 'revision')
            ->addSelect('revision')
            ->andWhere('publication.page IN (:pageIds)')
            ->setParameter('pageIds', array_map(static fn (string $id): string => Ulid::fromString($id)->toBinary(), $pageIds), ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult();

        $byPage = [];
        foreach ($publications as $publication) {
            $byPage[(string) $publication->page()->id()] = $publication;
        }

        return $byPage;
    }

    public function findPublishedByPath(string $path): ?PagePublication
    {
        $normalized = trim($path);
        if ($normalized !== '' && !str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        /** @var PagePublication|null $publication */
        $publication = $this->createQueryBuilder('publication')
            ->join('publication.page', 'page')
            ->addSelect('page')
            ->leftJoin('publication.publishedRevision', 'revision')
            ->addSelect('revision')
            ->andWhere('page.path = :path')
            ->andWhere('page.status = :status')
            ->andWhere('page.deletedAt IS NULL')
            ->andWhere('publication.publishedRevision IS NOT NULL')
            ->setParameter('path', $normalized)
            ->setParameter('status', PageStatus::Published)
            ->getQuery()
            ->getOneOrNullResult();

        return $publication;
    }
}
