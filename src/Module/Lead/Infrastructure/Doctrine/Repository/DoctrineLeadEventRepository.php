<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Doctrine\Repository;

use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Entity\LeadEvent;
use App\Module\Lead\Domain\Repository\LeadEventRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeadEvent>
 */
final class DoctrineLeadEventRepository extends ServiceEntityRepository implements LeadEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeadEvent::class);
    }

    public function save(LeadEvent $event): void
    {
        $this->getEntityManager()->persist($event);
        $this->getEntityManager()->flush();
    }

    public function findByLead(Lead $lead): array
    {
        /** @var list<LeadEvent> $events */
        $events = $this->createQueryBuilder('event')
            ->where('IDENTITY(event.lead) = :leadId')
            ->setParameter('leadId', $lead->id(), 'ulid')
            ->orderBy('event.createdAt', 'DESC')
            ->addOrderBy('event.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $events;
    }
}
