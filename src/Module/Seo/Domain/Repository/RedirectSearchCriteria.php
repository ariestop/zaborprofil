<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

final readonly class RedirectSearchCriteria
{
    public const string SORT_SOURCE = 'source';
    public const string SORT_HITS = 'hits';
    public const string SORT_UPDATED = 'updated';
    public const string SORT_LAST_HIT = 'lastHit';

    /**
     * @var list<string>
     */
    public const array SORTS = [self::SORT_SOURCE, self::SORT_HITS, self::SORT_UPDATED, self::SORT_LAST_HIT];

    public function __construct(
        public ?string $query = null,
        public ?bool $active = null,
        public string $sort = self::SORT_SOURCE,
        public bool $descending = false,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
