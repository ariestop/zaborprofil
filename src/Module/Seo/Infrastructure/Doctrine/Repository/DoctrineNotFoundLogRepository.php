<?php

declare(strict_types=1);

namespace App\Module\Seo\Infrastructure\Doctrine\Repository;

use App\Module\Seo\Domain\Entity\NotFoundLogEntry;
use App\Module\Seo\Domain\Repository\NotFoundLogRepositoryInterface;
use App\Module\Seo\Domain\Repository\NotFoundSearchCriteria;
use App\Module\Seo\Domain\Repository\NotFoundSearchResult;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<NotFoundLogEntry>
 */
final class DoctrineNotFoundLogRepository extends ServiceEntityRepository implements NotFoundLogRepositoryInterface
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotFoundLogEntry::class);
    }

    public function registerHit(string $path, ?string $referrer, DateTimeImmutable $seenAt, int $maxEntries): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $table = $this->getClassMetadata()->getTableName();
        $hash = NotFoundLogEntry::hashPath($path);
        $now = $seenAt->format(self::DATE_FORMAT);

        $increment = static fn (): int|string => $connection->executeStatement(
            \sprintf('UPDATE %s SET hit_count = hit_count + 1, last_seen_at = :now, referrer = COALESCE(:referrer, referrer) WHERE path_hash = :hash', $table),
            ['now' => $now, 'referrer' => $referrer, 'hash' => $hash],
        );

        if ($increment() > 0) {
            return;
        }

        $existing = self::toInt($connection->fetchOne(
            \sprintf('SELECT COUNT(*) FROM (SELECT 1 FROM %s LIMIT %d) AS limited', $table, $maxEntries),
        ));
        if ($existing >= $maxEntries) {
            return;
        }

        try {
            $connection->executeStatement(
                \sprintf('INSERT INTO %s (id, path, path_hash, hit_count, first_seen_at, last_seen_at, referrer) VALUES (:id, :path, :hash, 1, :now, :now, :referrer)', $table),
                ['id' => (new Ulid())->toBinary(), 'path' => $path, 'hash' => $hash, 'now' => $now, 'referrer' => $referrer],
                ['id' => ParameterType::BINARY],
            );
        } catch (UniqueConstraintViolationException) {
            $increment();
        }
    }

    public function search(NotFoundSearchCriteria $criteria): NotFoundSearchResult
    {
        $builder = $this->createQueryBuilder('entry');

        $query = $criteria->query !== null ? trim($criteria->query) : '';
        if ($query !== '') {
            $builder
                ->andWhere('entry.path LIKE :query')
                ->setParameter('query', '%'.addcslashes($query, '\\%_').'%');
        }

        $totals = (clone $builder)
            ->select('COUNT(entry.id) AS total', 'COALESCE(SUM(entry.hitCount), 0) AS hits')
            ->getQuery()
            ->getSingleResult();
        $total = \is_array($totals) ? self::toInt($totals['total'] ?? 0) : 0;
        $totalHits = \is_array($totals) ? self::toInt($totals['hits'] ?? 0) : 0;

        $perPage = max(1, $criteria->perPage);
        if ($criteria->sort === NotFoundSearchCriteria::SORT_LAST_SEEN) {
            $builder->orderBy('entry.lastSeenAt', 'DESC')->addOrderBy('entry.hitCount', 'DESC');
        } else {
            $builder->orderBy('entry.hitCount', 'DESC')->addOrderBy('entry.lastSeenAt', 'DESC');
        }

        /** @var list<NotFoundLogEntry> $items */
        $items = $builder
            ->setFirstResult((max(1, $criteria->page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new NotFoundSearchResult($items, $total, $totalHits);
    }

    public function findById(string $id): ?NotFoundLogEntry
    {
        if (!Ulid::isValid($id)) {
            return null;
        }

        return $this->find(Ulid::fromString($id));
    }

    public function remove(NotFoundLogEntry $entry): void
    {
        $this->getEntityManager()->remove($entry);
        $this->getEntityManager()->flush();
    }

    public function clear(): int
    {
        return self::toInt($this->getEntityManager()->createQuery(\sprintf('DELETE FROM %s entry', NotFoundLogEntry::class))->execute());
    }

    public function pruneOlderThan(DateTimeImmutable $threshold): int
    {
        return self::toInt($this->getEntityManager()
            ->createQuery(\sprintf('DELETE FROM %s entry WHERE entry.lastSeenAt < :threshold', NotFoundLogEntry::class))
            ->setParameter('threshold', $threshold)
            ->execute());
    }

    private static function toInt(mixed $value): int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && is_numeric($value) => (int) $value,
            default => 0,
        };
    }
}
