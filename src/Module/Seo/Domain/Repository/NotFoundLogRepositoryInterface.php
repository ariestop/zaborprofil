<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

use App\Module\Seo\Domain\Entity\NotFoundLogEntry;
use DateTimeImmutable;

interface NotFoundLogRepositoryInterface
{
    /**
     * Увеличивает счётчик существующей записи или создаёт новую, если лимит `$maxEntries` ещё не исчерпан.
     */
    public function registerHit(string $path, ?string $referrer, DateTimeImmutable $seenAt, int $maxEntries): void;

    public function search(NotFoundSearchCriteria $criteria): NotFoundSearchResult;

    public function findById(string $id): ?NotFoundLogEntry;

    public function remove(NotFoundLogEntry $entry): void;

    /**
     * @return int число удалённых записей
     */
    public function clear(): int;

    /**
     * @return int число удалённых записей
     */
    public function pruneOlderThan(DateTimeImmutable $threshold): int;
}
