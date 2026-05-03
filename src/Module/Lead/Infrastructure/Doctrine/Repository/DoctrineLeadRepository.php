<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Lead>
 */
final class DoctrineLeadRepository extends ServiceEntityRepository implements LeadRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    public function save(Lead $lead): void
    {
        $this->getEntityManager()->persist($lead);
        $this->getEntityManager()->flush();
    }

    public function get(string $id): Lead
    {
        return $this->find(Ulid::fromString($id)) ?? throw new ContentNotFoundException('Lead not found.');
    }

    public function findLatest(int $limit = 100): array
    {
        /** @var list<Lead> $result */
        $result = $this->createQueryBuilder('lead')
            ->orderBy('lead.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
