<?php

declare(strict_types=1);

namespace App\Module\Lead\Infrastructure\Doctrine\Repository;

use App\Module\Content\Domain\Exception\ContentNotFoundException;
use App\Module\Lead\Domain\Entity\Lead;
use App\Module\Lead\Domain\Repository\LeadRepositoryInterface;
use App\Module\Lead\Domain\Repository\LeadSearchCriteria;
use App\Module\Lead\Domain\Repository\LeadSearchResult;
use App\Module\Lead\Domain\ValueObject\LeadStatus;
use App\Module\Lead\Domain\ValueObject\PhoneNumber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
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
        if (!Ulid::isValid($id)) {
            throw new InvalidArgumentException('Lead id is not valid.');
        }

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

    public function search(LeadSearchCriteria $criteria): LeadSearchResult
    {
        $builder = $this->filtered($criteria);

        $total = (int) (clone $builder)
            ->select('COUNT(lead.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $perPage = max(1, $criteria->perPage);

        /** @var list<Lead> $items */
        $items = $this->sorted($builder, $criteria)
            ->setFirstResult((max(1, $criteria->page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new LeadSearchResult($items, $total);
    }

    public function findForExport(LeadSearchCriteria $criteria, int $limit): array
    {
        /** @var list<Lead> $items */
        $items = $this->sorted($this->filtered($criteria), $criteria)
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function countByStatus(LeadSearchCriteria $criteria): array
    {
        /** @var list<array{status: string, total: int|string}> $rows */
        $rows = $this->filtered($criteria->withoutStatus())
            ->select('lead.status AS status', 'COUNT(lead.id) AS total')
            ->groupBy('lead.status')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(LeadStatus::values(), 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function distinctSources(): array
    {
        /** @var list<array{source: string}> $rows */
        $rows = $this->createQueryBuilder('lead')
            ->select('DISTINCT lead.source AS source')
            ->orderBy('lead.source', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => $row['source'], $rows);
    }

    private function filtered(LeadSearchCriteria $criteria): QueryBuilder
    {
        $builder = $this->createQueryBuilder('lead');

        $query = $criteria->query !== null ? trim($criteria->query) : '';
        if ($query !== '') {
            $conditions = ['lead.name LIKE :query', 'lead.phone LIKE :query', 'lead.email LIKE :query', 'lead.message LIKE :query'];
            $builder->setParameter('query', '%'.addcslashes($query, '\\%_').'%');

            $digits = PhoneNumber::digits($query);
            if (\strlen($digits) >= 3 && preg_match('/^[\d\s+()\-]+$/', $query) === 1) {
                foreach (array_values(array_unique([$digits, ...self::prefixVariants($digits)])) as $index => $variant) {
                    $conditions[] = \sprintf('lead.phoneDigits LIKE :digits%d', $index);
                    $builder->setParameter('digits'.$index, '%'.$variant.'%');
                }
            }

            $builder->andWhere(implode(' OR ', $conditions));
        }

        if ($criteria->status !== null) {
            $builder->andWhere('lead.status = :status')->setParameter('status', $criteria->status);
        } elseif ($criteria->excludeSpam) {
            $builder->andWhere('lead.status <> :excludedStatus')->setParameter('excludedStatus', LeadStatus::SPAM);
        }

        if ($criteria->source !== null && $criteria->source !== '') {
            $builder->andWhere('lead.source = :source')->setParameter('source', $criteria->source);
        }

        if ($criteria->createdFrom !== null) {
            $builder->andWhere('lead.createdAt >= :createdFrom')->setParameter('createdFrom', $criteria->createdFrom, Types::DATETIME_IMMUTABLE);
        }

        if ($criteria->createdBefore !== null) {
            $builder->andWhere('lead.createdAt < :createdBefore')->setParameter('createdBefore', $criteria->createdBefore, Types::DATETIME_IMMUTABLE);
        }

        if ($criteria->b2bOnly) {
            $builder->andWhere('lead.b2b = true');
        }

        if ($criteria->waitingHours !== null) {
            $builder
                ->andWhere('lead.status = :waitingStatus')
                ->andWhere('lead.createdAt <= :waitingBefore')
                ->setParameter('waitingStatus', LeadStatus::NEW)
                ->setParameter('waitingBefore', new \DateTimeImmutable(\sprintf('-%d hours', $criteria->waitingHours)), Types::DATETIME_IMMUTABLE);
        }

        if ($criteria->assignee === LeadSearchCriteria::ASSIGNEE_NONE) {
            $builder->andWhere('lead.assigneeId IS NULL');
        } elseif ($criteria->assignee !== null && Ulid::isValid($criteria->assignee)) {
            $builder->andWhere('lead.assigneeId = :assignee')->setParameter('assignee', Ulid::fromString($criteria->assignee), 'ulid');
        }

        return $builder;
    }

    private function sorted(QueryBuilder $builder, LeadSearchCriteria $criteria): QueryBuilder
    {
        $sortField = match ($criteria->sort) {
            LeadSearchCriteria::SORT_UPDATED => 'lead.updatedAt',
            LeadSearchCriteria::SORT_NAME => 'lead.name',
            LeadSearchCriteria::SORT_STATUS => 'lead.status',
            LeadSearchCriteria::SORT_SOURCE => 'lead.source',
            default => 'lead.createdAt',
        };
        $direction = $criteria->descending ? 'DESC' : 'ASC';

        return $builder
            ->orderBy($sortField, $direction)
            ->addOrderBy('lead.id', $direction);
    }

    /**
     * Телефоны хранятся в виде «7…», поэтому неполный запрос «8 900…» ищется и с префиксом «7».
     *
     * @return list<string>
     */
    private static function prefixVariants(string $digits): array
    {
        return $digits[0] === '8' ? ['7'.substr($digits, 1)] : [];
    }
}
