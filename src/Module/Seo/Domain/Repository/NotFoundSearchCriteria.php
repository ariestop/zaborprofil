<?php

declare(strict_types=1);

namespace App\Module\Seo\Domain\Repository;

final readonly class NotFoundSearchCriteria
{
    public const string SORT_HITS = 'hits';
    public const string SORT_LAST_SEEN = 'lastSeen';

    /**
     * @var list<string>
     */
    public const array SORTS = [self::SORT_HITS, self::SORT_LAST_SEEN];

    public function __construct(
        public ?string $query = null,
        public string $sort = self::SORT_HITS,
        public int $page = 1,
        public int $perPage = 25,
    ) {
    }
}
