<?php

declare(strict_types=1);

namespace App\Module\AuditLog\Infrastructure\Doctrine\Repository;

use App\Module\AuditLog\Domain\Entity\AuditLogEntry;
use App\Module\AuditLog\Domain\Repository\AuditLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLogEntry>
 */
final class DoctrineAuditLogRepository extends ServiceEntityRepository implements AuditLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLogEntry::class);
    }

    public function save(AuditLogEntry $entry): void
    {
        $this->getEntityManager()->persist($entry);
        $this->getEntityManager()->flush();
    }

    public function findLatest(int $limit = 50): array
    {
        /** @var list<AuditLogEntry> $result */
        $result = $this->createQueryBuilder('entry')
            ->orderBy('entry.occurredAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByEntity(string $entityType, string $entityId, string $actionPrefix, int $limit = 50): array
    {
        /** @var list<AuditLogEntry> $result */
        $result = $this->createQueryBuilder('entry')
            ->andWhere('entry.entityType = :entityType')
            ->andWhere('entry.entityId = :entityId')
            ->andWhere('entry.action LIKE :prefix')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('prefix', addcslashes($actionPrefix, '%_\\').'%')
            ->orderBy('entry.occurredAt', 'DESC')
            ->addOrderBy('entry.id', 'DESC')
            ->setMaxResults(max(1, min($limit, 200)))
            ->getQuery()
            ->getResult();

        return $result;
    }
}
