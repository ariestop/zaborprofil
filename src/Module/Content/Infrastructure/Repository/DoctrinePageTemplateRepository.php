<?php

declare(strict_types=1);

namespace App\Module\Content\Infrastructure\Repository;

use App\Module\Content\Domain\Entity\PageTemplate;
use App\Module\Content\Domain\Enum\PageType;
use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Content\Domain\Repository\PageTemplateRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageTemplate>
 */
final class DoctrinePageTemplateRepository extends ServiceEntityRepository implements PageTemplateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageTemplate::class);
    }

    public function save(PageTemplate $template): void
    {
        $this->getEntityManager()->persist($template);
        $this->getEntityManager()->flush();
    }

    public function getByCode(string $code): PageTemplate
    {
        return $this->findOneBy(['code' => $code]) ?? throw new ContentNotFoundException('Page template not found.');
    }

    public function findByCode(string $code): ?PageTemplate
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findActive(?PageType $pageType = null, ?string $kind = PageTemplate::KIND_PAGE): array
    {
        $criteria = ['active' => true];
        if ($pageType !== null) {
            $criteria['pageType'] = $pageType;
        }
        if ($kind !== null) {
            $criteria['kind'] = $kind;
        }

        return $this->findBy($criteria, ['system' => 'DESC', 'name' => 'ASC']);
    }
}
